/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║     Rafiq — Sign Language Model  (sign-language-model.js)        ║
 * ╠══════════════════════════════════════════════════════════════════╣
 * ║                                                                  ║
 * ║  This file is the BRAIN of the detection pipeline.              ║
 * ║  It sits between the raw landmark data (MediaPipe) and the UI.  ║
 * ║                                                                  ║
 * ║  Responsibilities:                                               ║
 * ║    1. Phase state machine (detecting → thinking → confirmed)    ║
 * ║    2. Route frames to the correct backend (rule-based or AI)    ║
 * ║    3. Session statistics tracking                                ║
 * ║    4. AI model integration hooks                                 ║
 * ║    5. Gesture dataset management                                 ║
 * ║                                                                  ║
 * ║  Public API  (window.SLModel)                                    ║
 * ║  ─────────────────────────────────────────────────────────────  ║
 * ║  SLModel.init(datasetUrl)  → Promise<void>  load gesture data   ║
 * ║  SLModel.onFrame(lm, fsSinceHand) → PhaseResult                 ║
 * ║  SLModel.onNoHand()        → void                               ║
 * ║  SLModel.resetSession()    → void                               ║
 * ║  SLModel.getStats()        → SessionStats                       ║
 * ║  SLModel.getCategory(signId) → Category | null                  ║
 * ║  SLModel.getAllSigns()     → Sign[]                              ║
 * ║  SLModel.getMvpSigns()    → Sign[]                              ║
 * ║  SLModel.connectTrainedModel(fn) → void   (AI hook)             ║
 * ║  SLModel.PHASES            → phase constants                     ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * ════════════════════════════════════════════════════════════════════
 *  DETECTION PHASE FLOW
 * ────────────────────────────────────────────────────────────────────
 *
 *   [Camera On]
 *       │
 *       ▼
 *   IDLE ────► hand appears ────► DETECTING (0-8 frames)
 *                                       │
 *                                       ▼
 *                               THINKING (fillRatio 0–0.40)
 *                               "Analysing hand shape..."
 *                                       │
 *                          candidate emerges (fillRatio > 0.40)
 *                                       │
 *                                       ▼
 *                               ANALYZING (fillRatio 0.40–0.80)
 *                               "Detecting: HELLO..."
 *                                       │
 *                         confidence builds (fillRatio > 0.80)
 *                                       │
 *                                       ▼
 *                               CONFIRMING (fillRatio > 0.80)
 *                               "Almost confirmed..."
 *                                       │
 *                        ┌─────────────┤
 *                        │             │
 *                        ▼             ▼
 *                   CONFIRMED       UNKNOWN
 *                   "HELLO 94%"     "Unknown gesture"
 *
 * ════════════════════════════════════════════════════════════════════
 *  STATIC vs DYNAMIC SIGNS — WHAT THIS FILE HANDLES
 * ────────────────────────────────────────────────────────────────────
 *
 *  STATIC (single-pose, current rule-based classifier handles these):
 *    Hello, Yes, No, Thank You, ILY, Emergency, Help,
 *    Hospital, Water, One, Bad, Call Ambulance, Food, More
 *
 *  DYNAMIC (require motion + trained model — future roadmap):
 *    Please, Sorry, Hungry, Wheelchair,
 *    Caregiver, Interpreter, and all other motion-based signs.
 *    → See connectTrainedModel() / connectTrainedModelDynamic() below.
 *
 * ════════════════════════════════════════════════════════════════════
 */

(function (global) {
  'use strict';

  /* ════════════════════════════════════════════════════════════════
     §1  PHASE CONSTANTS
     ════════════════════════════════════════════════════════════════ */
  const PHASES = Object.freeze({
    IDLE:        'idle',        // Camera off / no hand
    DETECTING:   'detecting',   // Hand just appeared — first N frames
    THINKING:    'thinking',    // Collecting frames, low confidence
    ANALYZING:   'analyzing',   // Strong candidate emerging
    CONFIRMING:  'confirming',  // Filling the last 20 % before lock
    CONFIRMED:   'confirmed',   // Sign locked in
    UNKNOWN:     'unknown',     // Hand present, no recognised sign
  });

  /* ── Phase → UI metadata (used by the page to style each phase) ── */
  const PHASE_META = {
    [PHASES.IDLE]:       { dot: 'off',      label: 'Ready — start camera to begin',            color: '#94a3b8' },
    [PHASES.DETECTING]:  { dot: 'scanning', label: 'Waiting for hand…',                        color: '#60a5fa' },
    [PHASES.THINKING]:   { dot: 'scanning', label: 'Stabilising pose…',                        color: '#a78bfa' },
    [PHASES.ANALYZING]:  { dot: 'found',    label: 'Reading gesture…',                         color: '#34d399' },
    [PHASES.CONFIRMING]: { dot: 'found',    label: 'Almost confirmed — hold steady',            color: '#fbbf24' },
    [PHASES.CONFIRMED]:  { dot: 'locked',   label: 'Sign confirmed',                         color: '#16a34a' },
    [PHASES.UNKNOWN]:    { dot: 'warn',     label: 'Unrecognised — try a supported sign',      color: '#f97316' },
  };

  /* ════════════════════════════════════════════════════════════════
     §2  CONFIGURATION
     ════════════════════════════════════════════════════════════════ */
  const CFG = {
    DETECT_FRAMES:     8,     // Hand must be visible this many frames before analysis
    THINK_FILL_MAX:   0.40,   // fillRatio below this → THINKING phase
    ANALYZE_FILL_MAX: 0.80,   // fillRatio below this → ANALYZING phase
    // (above 0.80 → CONFIRMING; gesture confirmed → CONFIRMED)
  };

  /* ════════════════════════════════════════════════════════════════
     §3  SESSION STATISTICS
     ════════════════════════════════════════════════════════════════ */
  const _stats = {
    sessionStart:   null,
    totalConfirmed: 0,
    signCounts:     {},   // { signName: count }
    lastGesture:    null,
    lastConfAt:     null,
  };

  function _resetStats() {
    _stats.sessionStart   = Date.now();
    _stats.totalConfirmed = 0;
    _stats.signCounts     = {};
    _stats.lastGesture    = null;
    _stats.lastConfAt     = null;
  }

  function _recordConfirmation(name) {
    _stats.totalConfirmed++;
    _stats.signCounts[name] = (_stats.signCounts[name] || 0) + 1;
    _stats.lastGesture = name;
    _stats.lastConfAt  = Date.now();
  }

  function _getStats() {
    const elapsed = _stats.sessionStart ? Math.floor((Date.now() - _stats.sessionStart) / 1000) : 0;
    const mins    = String(Math.floor(elapsed / 60)).padStart(2, '0');
    const secs    = String(elapsed % 60).padStart(2, '0');

    let topSign = null, topCount = 0;
    for (const [s, c] of Object.entries(_stats.signCounts)) {
      if (c > topCount) { topSign = s; topCount = c; }
    }

    return {
      total:    _stats.totalConfirmed,
      duration: `${mins}:${secs}`,
      topSign:  topSign ? `${topSign} (${topCount}×)` : '—',
      lastSign: _stats.lastGesture,
    };
  }

  /* ════════════════════════════════════════════════════════════════
     §4  GESTURE DATASET
     ════════════════════════════════════════════════════════════════ */
  let _dataset    = null;   // Full gesture-dataset.json content
  let _signMap    = {};     // { signId: signObject }
  let _catMap     = {};     // { catId: categoryObject }

  async function _loadDataset(url) {
    try {
      const res  = await fetch(url);
      _dataset   = await res.json();
      _signMap   = {};
      _catMap    = {};
      (_dataset.categories || []).forEach(c => { _catMap[c.id] = c; });
      (_dataset.signs       || []).forEach(s => { _signMap[s.id] = s; });
    } catch (e) {
      console.warn('[SLModel] Could not load gesture dataset:', e.message);
    }
  }

  function _normaliseSignKey(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/&/g, 'and')
      .replace(/\s*\/\s*.*/g, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function _getCategory(signIdOrName) {
    const direct = _signMap[signIdOrName];
    if (direct) return _catMap[direct.category] || null;

    const key = _normaliseSignKey(signIdOrName);
    const byKey = _signMap[key];
    if (byKey) return _catMap[byKey.category] || null;

    const byName = Object.values(_signMap).find(s => _normaliseSignKey(s.name) === key);
    if (!byName) return null;

    return _catMap[byName.category] || null;
  }

  function _getAllSigns()  { return _dataset ? [..._dataset.signs] : []; }
  function _getMvpSigns()  { return _getAllSigns().filter(s => s.mvp); }
  function _getCategories(){ return _dataset ? [..._dataset.categories] : []; }

  /* ════════════════════════════════════════════════════════════════
     §5  AI MODEL HOOK
     ════════════════════════════════════════════════════════════════
     By default the model uses SLClassifier (rule-based, installed).
     Call connectTrainedModel(fn) to swap in a real AI model.

     ── Static Model (single frame) ─────────────────────────────────
     connectTrainedModel(async (landmarks) => {
       // landmarks: 21 MediaPipe Hand Landmark objects {x,y,z}
       // Must return: { gesture: string|null, confidence: number 0-1,
       //               scores: {name:score}, isUnknown: boolean }

       const input = tf.tensor2d([landmarks.flatMap(lm=>[lm.x,lm.y,lm.z||0])]);
       const probs = await model.predict(input).data();
       input.dispose();
       const CLASS_NAMES = ['Hello','Yes','No','Help','Water',
                            'Hospital','Emergency','Thank you',
                            'ILY','Bad','One'];
       const maxIdx = [...probs].indexOf(Math.max(...probs));
       return {
         gesture:   probs[maxIdx] > 0.55 ? CLASS_NAMES[maxIdx] : null,
         confidence: probs[maxIdx],
         scores:    Object.fromEntries(CLASS_NAMES.map((n,i)=>[n,probs[i]])),
         isUnknown: probs[maxIdx] <= 0.55,
       };
     });

     ── Dynamic / Sequence Model (LSTM) ─────────────────────────────
     connectTrainedModelDynamic(async (landmarkBuffer) => {
       // landmarkBuffer: last 30 frames of 21 landmarks each
       // Input tensor shape: [1, 30, 63]
       const seq   = landmarkBuffer.map(lm=>lm.flatMap(p=>[p.x,p.y,p.z||0]));
       const input = tf.tensor3d([seq]);
       const probs = await lstmModel.predict(input).data();
       input.dispose();
       // ... return same shape as static model
     });

     ── ONNX Runtime Web alternative ────────────────────────────────
     connectTrainedModel(async (landmarks) => {
       const flat    = new Float32Array(landmarks.flatMap(lm=>[lm.x,lm.y,lm.z||0]));
       const input   = new ort.Tensor('float32', flat, [1, 63]);
       const results = await ortSession.run({ input });
       const probs   = results.output.data;
       // ... same indexing
     });
     ════════════════════════════════════════════════════════════════ */

  let _trainedModelFn         = null;   // static-frame AI model
  let _trainedModelDynamicFn  = null;   // sequence/LSTM AI model
  let _usingAI                = false;

  function _connectTrainedModel(fn) {
    _trainedModelFn = fn;
    _usingAI        = true;
    console.info('[SLModel] Trained static model connected.');
  }

  function _connectTrainedModelDynamic(fn) {
    _trainedModelDynamicFn = fn;
    _usingAI               = true;
    console.info('[SLModel] Trained dynamic/LSTM model connected.');
  }

  /* ════════════════════════════════════════════════════════════════
     §6  LANDMARK BUFFER (for dynamic model)
     ════════════════════════════════════════════════════════════════ */
  const LANDMARK_BUFFER_SIZE = 30;
  const _landmarkBuffer = [];

  function _pushLandmarks(lm) {
    _landmarkBuffer.push(lm);
    if (_landmarkBuffer.length > LANDMARK_BUFFER_SIZE) _landmarkBuffer.shift();
  }

  /*
   * Built-in lightweight dynamic heuristic.
   * This is not a trained LSTM, but it lets the website read one safe motion sign:
   * Goodbye = open hand + clear side-to-side wrist movement over recent frames.
   * It only overrides when the current handshape already looks like Hello/open palm.
   */
  function _builtInDynamicResult(classResult) {
    if (_landmarkBuffer.length < 18) return null;

    const recent = _landmarkBuffer.slice(-18);
    const latest = recent[recent.length - 1];
    const latestFeatures = SLClassifier.extractFeatures(latest);

    const helloScore = classResult.scores && classResult.scores.Hello ? classResult.scores.Hello : 0;
    const isOpenPalm = helloScore >= 0.78;
    if (!isOpenPalm) return null;

    const wristXs = recent.map(frame => frame[0].x);
    const minX = Math.min(...wristXs);
    const maxX = Math.max(...wristXs);
    const amp = maxX - minX;

    // Count direction changes so a one-way drift does not count as a wave.
    const diffs = [];
    for (let i = 1; i < wristXs.length; i++) {
      const d = wristXs[i] - wristXs[i - 1];
      if (Math.abs(d) > 0.004) diffs.push(Math.sign(d));
    }

    let turns = 0;
    for (let i = 1; i < diffs.length; i++) {
      if (diffs[i] !== diffs[i - 1]) turns++;
    }

    // Normalised camera x amplitude; 0.045-0.06 is a real wave on most webcams.
    if (amp >= 0.045 && turns >= 1) {
      const confidence = Math.min(0.94, Math.max(0.84, 0.80 + amp * 2.1 + turns * 0.03));
      const scores = Object.assign({}, classResult.scores || {});
      scores.Goodbye = confidence;
      return {
        gesture: 'Goodbye',
        confidence,
        scores,
        isUnknown: false,
      };
    }

    return null;
  }

  /* ════════════════════════════════════════════════════════════════
     §7  CORE FRAME PROCESSOR
     ════════════════════════════════════════════════════════════════ */

  /**
   * onFrame(landmarks, framesSinceHand)
   *
   * Called every frame when a hand is detected.
   *
   * @param  landmarks       — 21 MediaPipe landmark objects {x,y,z}
   * @param  framesSinceHand — how many consecutive frames the hand has been visible
   * @returns PhaseResult:
   *   { phase, gesture, candidate, confidence, fillRatio, isNew, scores, meta }
   */
  async function _onFrame(landmarks, framesSinceHand) {
    _pushLandmarks(landmarks);

    /* ── Phase 1: DETECTING — hand just appeared ── */
    if (framesSinceHand < CFG.DETECT_FRAMES) {
      return _makeResult(PHASES.DETECTING, {
        fillRatio: framesSinceHand / CFG.DETECT_FRAMES,
      });
    }

    /* ── Classify this frame ── */
    let classResult;
    if (_trainedModelFn) {
      // AI static model is connected
      try {
        classResult = await _trainedModelFn(landmarks);
      } catch (e) {
        console.warn('[SLModel] Trained model error, falling back to rule-based:', e);
        classResult = SLClassifier.classify(landmarks);
      }
    } else {
      // Default: rule-based geometric classifier
      classResult = SLClassifier.classify(landmarks);
    }

    /* ── Dynamic model (if connected and buffer is full) ── */
    if (_trainedModelDynamicFn && _landmarkBuffer.length >= LANDMARK_BUFFER_SIZE) {
      try {
        const dynResult = await _trainedModelDynamicFn([..._landmarkBuffer]);
        // Use dynamic result if it has higher confidence than static
        if (dynResult.confidence > (classResult.confidence || 0) + 0.05) {
          classResult = dynResult;
        }
      } catch (e) {
        console.warn('[SLModel] Dynamic model error:', e);
      }
    }

    /* ── Built-in dynamic heuristic for safe motion signs such as Goodbye ── */
    const builtInDynamic = _builtInDynamicResult(classResult);
    if (builtInDynamic && builtInDynamic.confidence > (classResult.confidence || 0) + 0.04) {
      classResult = builtInDynamic;
    }

    /* ── Smooth ── */
    const smoothed = SLClassifier.smooth(classResult);

    /* ── Determine phase ── */
    let phase;
    if (smoothed.gesture && smoothed.fillRatio >= 1) {
      phase = PHASES.CONFIRMED;
    } else if (classResult.isUnknown && smoothed.fillRatio < 0.25) {
      phase = PHASES.UNKNOWN;
    } else if (smoothed.fillRatio >= CFG.ANALYZE_FILL_MAX) {
      phase = PHASES.CONFIRMING;
    } else if (smoothed.fillRatio >= CFG.THINK_FILL_MAX && smoothed.candidate) {
      phase = PHASES.ANALYZING;
    } else {
      phase = PHASES.THINKING;
    }

    /* ── Record confirmed ── */
    if (phase === PHASES.CONFIRMED && smoothed.isNew) {
      _recordConfirmation(smoothed.gesture);
    }

    return _makeResult(phase, {
      gesture:    smoothed.gesture,
      candidate:  smoothed.candidate || classResult.gesture,
      confidence: smoothed.confidence,
      fillRatio:  smoothed.fillRatio,
      isNew:      smoothed.isNew,
      scores:     classResult.scores || {},
      isUnknown:  classResult.isUnknown,
    });
  }

  /** Build a standardised result object */
  function _makeResult(phase, overrides) {
    return Object.assign({
      phase,
      gesture:    null,
      candidate:  null,
      confidence: 0,
      fillRatio:  0,
      isNew:      false,
      scores:     {},
      isUnknown:  false,
      meta:       PHASE_META[phase] || PHASE_META[PHASES.IDLE],
    }, overrides, { meta: PHASE_META[phase] || PHASE_META[PHASES.IDLE] });
  }

  /** Called when the hand disappears from frame */
  function _onNoHand() {
    SLClassifier.resetSmoother();
    _landmarkBuffer.length = 0;
  }

  /** Full session reset */
  function _resetSession() {
    _onNoHand();
    _resetStats();
  }

  /* ════════════════════════════════════════════════════════════════
     §8  PUBLIC API EXPORT
     ════════════════════════════════════════════════════════════════ */
  global.SLModel = {
    // Setup
    init:   _loadDataset,

    // Per-frame processing
    onFrame:   _onFrame,
    onNoHand:  _onNoHand,

    // Session
    resetSession: _resetSession,
    getStats:     _getStats,

    // Dataset queries
    getCategory:  _getCategory,
    getAllSigns:   _getAllSigns,
    getMvpSigns:  _getMvpSigns,
    getCategories: _getCategories,

    // AI model hooks
    connectTrainedModel:        _connectTrainedModel,
    connectTrainedModelDynamic: _connectTrainedModelDynamic,
    isUsingAI: () => _usingAI,

    // Constants
    PHASES,
    PHASE_META,
    CFG,
  };

})(window);
