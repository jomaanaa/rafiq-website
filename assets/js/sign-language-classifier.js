/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║     Rafiq — Sign Language Gesture Classifier  v3                 ║
 * ╠══════════════════════════════════════════════════════════════════╣
 * ║                                                                  ║
 * ║  Architecture                                                    ║
 * ║  ─────────────────────────────────────────────────────────────  ║
 * ║  1.  3-D Vector math  (v3, dot3, mag3, norm3, cross3, angle3)   ║
 * ║  2.  Hand geometry    (handScale, palmCenter, palmNormal,        ║
 * ║                        fingerBend, thumbFeatures)                ║
 * ║  3.  Feature bundle   (extractFeatures)                          ║
 * ║  4.  Smooth scoring   (fallsBelow, risesAbove, clamp01, geo)    ║
 * ║  5.  Per-sign scorers (8 dedicated functions)                    ║
 * ║  6.  Main classifier  (classify → { gesture, confidence,        ║
 * ║                                     scores, isUnknown })         ║
 * ║  7.  Expo-weighted smoother (smooth / resetSmoother)             ║
 * ║  8.  Utility         (checkLighting)                             ║
 * ║  9.  AI-model hook   (connectTrainedModel — see bottom)          ║
 * ║                                                                  ║
 * ║  Public API (window.SLClassifier)                                ║
 * ║  ─────────────────────────────────────────────────────────────  ║
 * ║  .classify(landmarks)  → { gesture, confidence, scores,         ║
 * ║                            isUnknown }                           ║
 * ║  .smooth(classifyResult) → { gesture, confidence, fillRatio,    ║
 * ║                              isNew, candidate }                  ║
 * ║  .resetSmoother()                                                ║
 * ║  .checkLighting(ctx, w, h) → brightness 0-255                   ║
 * ║  .extractFeatures(lm) → raw feature bundle (for debugging)      ║
 * ║  .SIGNS                → dictionary of signs + metadata          ║
 * ║  .PARAMS               → tunable constants                       ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 *  MediaPipe Hand Landmark indices (21 points, 0-20):
 *  ─────────────────────────────────────────────────
 *  0  WRIST
 *  1  THUMB_CMC   2  THUMB_MCP   3  THUMB_IP    4  THUMB_TIP
 *  5  INDEX_MCP   6  INDEX_PIP   7  INDEX_DIP   8  INDEX_TIP
 *  9  MIDDLE_MCP  10 MIDDLE_PIP  11 MIDDLE_DIP  12 MIDDLE_TIP
 *  13 RING_MCP    14 RING_PIP    15 RING_DIP    16 RING_TIP
 *  17 PINKY_MCP   18 PINKY_PIP   19 PINKY_DIP   20 PINKY_TIP
 *
 *  Coordinate system:  x=0 left → x=1 right
 *                      y=0 top  → y=1 bottom   (y GROWS downward)
 *                      z = depth relative to wrist (–=closer to cam)
 *
 * ════════════════════════════════════════════════════════════════════
 *  STATIC vs DYNAMIC signs
 * ────────────────────────────────────────────────────────────────────
 *  STATIC (detectable by single-frame pose — this classifier):
 *    Hello, Yes, No, Help, Water, Hospital, Emergency, Thank you
 *
 *  DYNAMIC (require motion sequences — needs LSTM/temporal model):
 *    "Please", "Sorry", "More", "Stop", "Come here",
 *    ASL alphabet letters that use movement (J, Z, …),
 *    continuous sign language sentences.
 *
 *  To add dynamic gestures, collect a time-series of landmark frames
 *  and feed them to a sequence model (LSTM / Transformer).
 *  See connectTrainedModel() at the bottom of this file.
 * ════════════════════════════════════════════════════════════════════
 */

(function (global) {
  'use strict';

  /* ════════════════════════════════════════════════════════════════
     §1  TUNABLE PARAMETERS
     — Adjust these to tune sensitivity without touching the math.
     ════════════════════════════════════════════════════════════════ */
  const PARAMS = {
    // ── Smoother ──────────────────────────────────────────────────
    BUFFER_FRAMES:   36,   // Number of frames in the rolling window (~1.2 s at 30 fps)
    EXP_DECAY:       0.88, // Weight of frame age: recent frames count more (0-1)
    MIN_BUFFER_FILL: 0.80, // Fraction of buffer needed before we can confirm (0-1)
    MIN_CONFIRM:     0.84, // Weighted-vote ratio required to confirm a gesture (0-1)
    COOLDOWN_MS:     2000, // Minimum ms between two fires of the SAME gesture

    // ── Classifier ────────────────────────────────────────────────
    MIN_CONFIDENCE:  0.72, // Raw classify() score below this → gesture:null (Unknown)
    MIN_SEPARATION:  0.18, // Top-vs-second score gap required; below → penalise confidence

    // ── Finger geometry ───────────────────────────────────────────
    // Bend angle thresholds (degrees; 0° = fully straight, 90°+ = fist)
    EXT_THRESH:      38,   // bend < EXT_THRESH  → finger is extended
    CURL_THRESH:     68,   // bend > CURL_THRESH → finger is curled
    SIGMOID_SOFT:    10,   // sigmoid softness (degrees); smaller = sharper step

    // ── Thumb geometry ────────────────────────────────────────────
    THUMB_UP_MIN:    0.75, // thumbUpward > this → thumb is pointing up      (Yes)
    THUMB_OUT_MIN:   0.42, // thumbToIdx  > this → thumb is spread sideways  (Hello, Thank you)
    THUMB_TUCK_MAX:  0.38, // thumbToPalm < this → thumb is tucked to palm   (Help, Hospital…)
    THUMB_CURL_DEG:  55,   // thumbBend   > this → thumb is curled/bent
  };

  /* ════════════════════════════════════════════════════════════════
     §2  SIGNS DICTIONARY
     ════════════════════════════════════════════════════════════════ */
  const SIGNS = {
    'Hello':     { desc: 'Open palm — all 5 fingers spread wide',                        isStatic: true },
    'Yes':       { desc: 'Thumb up — four fingers curled in fist',                        isStatic: true },
    'No':        { desc: 'Index + pinky up, middle + ring + thumb folded',                isStatic: true },
    'Help':      { desc: 'Four fingers straight up, thumb tucked across palm',            isStatic: true },
    'Water':     { desc: 'Index, middle & ring up (W shape) — thumb + pinky folded',     isStatic: true },
    'Hospital':  { desc: 'Peace / V sign — index + middle only, thumb folded',           isStatic: true },
    'Emergency': { desc: 'Closed fist — all fingers curled tightly',                     isStatic: true },
    'Thank you': { desc: 'Thumb + index + middle up — ring + pinky folded',              isStatic: true },
    'ILY':       { desc: 'Thumb + index + pinky up — middle + ring folded (ASL ILY)',   isStatic: true },
    'Bad':       { desc: 'Thumbs DOWN — fist with thumb pointing toward the floor',      isStatic: true },
    'One':       { desc: 'Index finger only extended up — all others closed',            isStatic: true },
    'Call Ambulance': { desc: 'Phone/Y handshape — thumb and pinky extended',             isStatic: true },
    'Food / Eat': { desc: 'Fingertips pinched together near thumb',                       isStatic: true },
    'More':       { desc: 'All fingertips pinched toward thumb',                          isStatic: true },
  };

  /* ════════════════════════════════════════════════════════════════
     §3  3-D VECTOR MATH
     ════════════════════════════════════════════════════════════════ */

  /** Vector from landmark a to landmark b */
  function v3(a, b) {
    return { x: b.x - a.x, y: b.y - a.y, z: (b.z || 0) - (a.z || 0) };
  }

  /** Dot product */
  function dot3(u, v) { return u.x * v.x + u.y * v.y + u.z * v.z; }

  /** Magnitude */
  function mag3(v) { return Math.sqrt(v.x * v.x + v.y * v.y + v.z * v.z); }

  /** Normalise (safe — returns zero-vector on degenerate input) */
  function norm3(v) {
    const m = mag3(v);
    return m < 1e-9 ? { x: 0, y: 0, z: 0 } : { x: v.x / m, y: v.y / m, z: v.z / m };
  }

  /** Cross product */
  function cross3(u, v) {
    return {
      x: u.y * v.z - u.z * v.y,
      y: u.z * v.x - u.x * v.z,
      z: u.x * v.y - u.y * v.x,
    };
  }

  /**
   * Angle (degrees) between two vectors.
   * Clamped to [0, 180] to avoid NaN from floating-point drift.
   */
  function angleDeg(u, v) {
    const d = dot3(norm3(u), norm3(v));
    return Math.acos(Math.max(-1, Math.min(1, d))) * (180 / Math.PI);
  }

  /** Euclidean distance between two landmarks */
  function dist3D(a, b) { return mag3(v3(a, b)); }

  /* ════════════════════════════════════════════════════════════════
     §4  HAND GEOMETRY HELPERS
     ════════════════════════════════════════════════════════════════ */

  /**
   * Hand scale: wrist (0) → middle-finger MCP (9).
   * Used to normalise all distance measurements so they are
   * independent of how close the hand is to the camera.
   */
  function handScale(lm) { return dist3D(lm[0], lm[9]) || 0.001; }

  /**
   * Palm centre: average of wrist + four MCP knuckles.
   */
  function palmCenter(lm) {
    const pts = [lm[0], lm[5], lm[9], lm[13], lm[17]];
    const n   = pts.length;
    return {
      x: pts.reduce((s, p) => s + p.x, 0) / n,
      y: pts.reduce((s, p) => s + p.y, 0) / n,
      z: pts.reduce((s, p) => s + (p.z || 0), 0) / n,
    };
  }

  /**
   * Palm normal vector via cross product of two palm edge vectors:
   *   edge1 = wrist → index MCP
   *   edge2 = wrist → pinky MCP
   *   normal = cross(edge1, edge2)
   *
   * When the palm faces the camera:
   *   normal.z is negative (points toward cam in MediaPipe's depth axis).
   */
  function palmNormal(lm) {
    const e1 = v3(lm[0], lm[5]);   // wrist → index MCP
    const e2 = v3(lm[0], lm[17]);  // wrist → pinky MCP
    return norm3(cross3(e1, e2));
  }

  /**
   * Finger bend angle at a given joint (degrees).
   *
   *   Method: compute vectors A→B and B→C, then find the angle at B.
   *   When the finger is perfectly straight, A, B, C are collinear:
   *     v1 = B→A (toward palm) and v2 = B→C (toward fingertip) oppose each other → 180°.
   *   As the finger curls, the angle decreases toward 0°.
   *   We return:  bendAngle = 180 − jointAngle
   *   so that  0° = straight  and  ≥ 90° = curled fist.
   *
   *   WHY THIS IS BETTER THAN Y-COORDINATE COMPARISON:
   *   A y-coordinate check only works when the hand points straight up.
   *   The angle method works regardless of hand orientation because
   *   it measures the internal geometry of the finger, not its
   *   position in the camera frame.
   */
  function fingerBend(lm, a_i, b_i, c_i) {
    const va = v3(lm[b_i], lm[a_i]); // B → A
    const vc = v3(lm[b_i], lm[c_i]); // B → C
    return 180 - angleDeg(va, vc);    // 0 = straight, 90+ = curled
  }

  /**
   * All four finger bend angles.
   * Uses MCP → PIP → TIP (skips DIP to reduce landmark noise).
   */
  function allFingerBends(lm) {
    return {
      i: fingerBend(lm, 5,  6,  8),   // index
      m: fingerBend(lm, 9,  10, 12),  // middle
      r: fingerBend(lm, 13, 14, 16),  // ring
      p: fingerBend(lm, 17, 18, 20),  // pinky
    };
  }

  /**
   * Thumb feature bundle.
   *
   *  thumbBend   — bend angle at IP joint (CMC→MCP→TIP); high = curled
   *  thumbUpward — (wrist.y − tip.y) / scale; positive = tip above wrist
   *                (image y grows DOWN, so smaller y = higher in frame)
   *  thumbToIdx  — dist(tip → index MCP) / scale; large = thumb spread out
   *  thumbToPalm — dist(tip → palmCentre) / scale; small = thumb tucked in
   */
  function thumbFeatures(lm, scale, pc) {
    return {
      bend:     fingerBend(lm, 1, 2, 4),
      upward:   (lm[0].y - lm[4].y) / scale,
      toIdx:    dist3D(lm[4], lm[5]) / scale,
      toPalm:   dist3D(lm[4], pc)   / scale,
    };
  }

  /* ════════════════════════════════════════════════════════════════
     §5  FULL FEATURE EXTRACTION
     ════════════════════════════════════════════════════════════════ */

  /**
   * Extract all geometric features from 21 landmarks.
   * Returned object is the single source of truth for all scorers.
   */
  function extractFeatures(lm) {
    const scale = handScale(lm);
    const pc    = palmCenter(lm);
    const bends = allFingerBends(lm);
    const thumb = thumbFeatures(lm, scale, pc);
    const pn    = palmNormal(lm);

    return { scale, pc, bends, thumb, palmNormal: pn, lm };
  }

  /* ════════════════════════════════════════════════════════════════
     §6  SMOOTH SCORING FUNCTIONS
     ════════════════════════════════════════════════════════════════ */

  /**
   * Sigmoid: returns ≈1 when value is BELOW threshold, ≈0 above it.
   * Use for:  "finger is extended"  (bend angle below EXT_THRESH)
   */
  function fallsBelow(value, threshold, softness) {
    softness = softness || PARAMS.SIGMOID_SOFT;
    return 1 / (1 + Math.exp((value - threshold) / softness));
  }

  /**
   * Sigmoid: returns ≈1 when value is ABOVE threshold, ≈0 below it.
   * Use for:  "finger is curled"    (bend angle above CURL_THRESH)
   */
  function risesAbove(value, threshold, softness) {
    softness = softness || PARAMS.SIGMOID_SOFT;
    return 1 / (1 + Math.exp(-(value - threshold) / softness));
  }

  /** Clamp a number to [0, 1] */
  function clamp01(v) { return Math.max(0, Math.min(1, v)); }

  /**
   * Geometric mean of all arguments.
   * Used for "ALL conditions must be met":
   *   if any single factor is near 0, the product collapses to near 0.
   * This prevents a gesture from scoring well when only some features match.
   */
  function geo(...vals) {
    if (!vals.length) return 0;
    const product = vals.reduce((p, v) => p * Math.max(0, v), 1);
    return Math.pow(product, 1 / vals.length);
  }

  /* ════════════════════════════════════════════════════════════════
     §7  PER-GESTURE CONFIDENCE SCORERS
     ════════════════════════════════════════════════════════════════
     Each function receives:
       f  — full feature bundle from extractFeatures()
       p  — PARAMS shortcut
     Returns: confidence score 0–1.

     Design principle: identify which features UNIQUELY discriminate
     this gesture from its nearest neighbour, and weight those highest.
     ════════════════════════════════════════════════════════════════ */

  const { EXT_THRESH: ET, CURL_THRESH: CT, SIGMOID_SOFT: SS } = PARAMS;

  /**
   * HELLO  — All 5 fingers extended, thumb spread outward.
   *
   *  Nearest neighbour: Help (same 4 fingers, but thumb tucked)
   *  Discriminator:     thumb must be spread AWAY from index MCP
   */
  function scoreHello(f) {
    const { bends: b, thumb: t } = f;
    const iExt = fallsBelow(b.i, ET, SS);
    const mExt = fallsBelow(b.m, ET, SS);
    const rExt = fallsBelow(b.r, ET, SS);
    const pExt = fallsBelow(b.p, ET, SS);
    // Thumb must be spread outward (large distance from index MCP)
    const tOut = risesAbove(t.toIdx, PARAMS.THUMB_OUT_MIN, 0.08);
    // Thumb must NOT be tucked
    const tNotTuck = fallsBelow(t.toPalm, 0.50, 0.10); // ← soft check, palm dist medium
    // Use geometric mean so ALL fingers and the thumb must be extended
    return geo(iExt, mExt, rExt, pExt, tOut, tNotTuck);
  }

  /**
   * YES  — Thumb pointing UP, four fingers tightly curled into fist.
   *
   *  Nearest neighbour: Emergency (also a fist but thumb across, not up)
   *  Discriminator:     thumbUpward (tip significantly above wrist in image)
   */
  function scoreYes(f) {
    const { bends: b, thumb: t } = f;
    const iCurl = risesAbove(b.i, CT, SS);
    const mCurl = risesAbove(b.m, CT, SS);
    const rCurl = risesAbove(b.r, CT, SS);
    const pCurl = risesAbove(b.p, CT, SS);
    // Thumb tip must be ABOVE wrist level — key discriminator from Emergency
    const tUp = risesAbove(t.upward, PARAMS.THUMB_UP_MIN, 0.25);
    // Thumb should NOT be heavily bent (it points up, not curled into fist)
    const tNotCurled = fallsBelow(t.bend, PARAMS.THUMB_CURL_DEG, 12);
    return geo(iCurl, mCurl, rCurl, pCurl, tUp, tNotCurled);
  }

  /**
   * NO  — Index + Pinky extended (horns / ILY variant).
   *         Middle + Ring curled. Thumb folded.
   *
   *  Nearest neighbour: Help (similar but ring is also up in Help)
   *  Discriminator:     ring must be CURLED and pinky must be EXTENDED
   */
  function scoreNo(f) {
    const { bends: b, thumb: t } = f;
    const iExt  = fallsBelow(b.i, ET, SS);       // index up
    const pExt  = fallsBelow(b.p, ET, SS);       // pinky up
    const mCurl = risesAbove(b.m, CT, SS);       // middle curled
    const rCurl = risesAbove(b.r, CT, SS);       // ring curled
    // Thumb should not be sticking out laterally
    const tIn   = fallsBelow(t.toIdx, 0.40, 0.08);
    return geo(iExt, pExt, mCurl, rCurl, tIn);
  }

  /**
   * HELP  — Four fingers straight up, thumb TUCKED across palm.
   *
   *  Nearest neighbour: Hello (same finger pattern; thumb is the only difference)
   *  Discriminator:     thumb close to palm (tucked), NOT spread outward
   */
  function scoreHelp(f) {
    const { bends: b, thumb: t } = f;
    const iExt   = fallsBelow(b.i, ET, SS);
    const mExt   = fallsBelow(b.m, ET, SS);
    const rExt   = fallsBelow(b.r, ET, SS);
    const pExt   = fallsBelow(b.p, ET, SS);
    // Thumb must be TUCKED — close to palm, NOT spread out
    const tTuck  = fallsBelow(t.toPalm, PARAMS.THUMB_TUCK_MAX, 0.08);
    const tNotOut = fallsBelow(t.toIdx, PARAMS.THUMB_OUT_MIN, 0.08);
    return geo(iExt, mExt, rExt, pExt, tTuck, tNotOut);
  }

  /**
   * WATER  — Index, Middle, Ring extended (W shape).
   *            Pinky curled. Thumb folded.
   *
   *  Nearest neighbour: Hospital (ring is also DOWN in Hospital)
   *  Discriminator:     ring EXTENDED (not curled like Hospital)
   */
  function scoreWater(f) {
    const { bends: b, thumb: t } = f;
    const iExt  = fallsBelow(b.i, ET, SS);
    const mExt  = fallsBelow(b.m, ET, SS);
    const rExt  = fallsBelow(b.r, ET, SS);       // ← key: ring IS extended
    const pCurl = risesAbove(b.p, CT, SS);       // pinky curled
    const tNotOut = fallsBelow(t.toIdx, 0.38, 0.08); // thumb not spread
    return geo(iExt, mExt, rExt, pCurl, tNotOut);
  }

  /**
   * HOSPITAL  — Index + Middle only (peace / V sign).
   *               Ring + Pinky curled. Thumb folded.
   *
   *  Nearest neighbour: Water (ring is UP in Water, DOWN here)
   *  Nearest neighbour: Thank you (thumb is UP in Thank you, DOWN here)
   *  Discriminator:     ring curled AND thumb not extended
   */
  function scoreHospital(f) {
    const { bends: b, thumb: t } = f;

    // Hospital is a V/peace handshape. It was previously too easy to trigger,
    // so we now require a clear V plus a folded thumb.
    const iExt     = fallsBelow(b.i, 28, 8);
    const mExt     = fallsBelow(b.m, 28, 8);
    const rCurl    = risesAbove(b.r, 76, 8);
    const pCurl    = risesAbove(b.p, 76, 8);
    const tFolded  = fallsBelow(t.toPalm, 0.42, 0.06);
    const tNotOut  = fallsBelow(t.toIdx, 0.34, 0.06);

    // Extra separation check: Water has ring extended, Thank you has thumb out.
    return geo(iExt, mExt, rCurl, pCurl, tFolded, tNotOut);
  }

  /**
   * EMERGENCY  — Closed fist, ALL fingers curled tightly.
   *
   *  Nearest neighbour: Yes (also a fist, but thumb points up)
   *  Discriminator:     thumbUpward must be LOW (thumb is not pointing up)
   */
  function scoreEmergency(f) {
    const { bends: b, thumb: t } = f;
    const iCurl = risesAbove(b.i, CT, SS);
    const mCurl = risesAbove(b.m, CT, SS);
    const rCurl = risesAbove(b.r, CT, SS);
    const pCurl = risesAbove(b.p, CT, SS);
    // Thumb is NOT pointing up — either curled across fist or to the side
    const tNotUp = fallsBelow(t.upward, PARAMS.THUMB_UP_MIN - 0.15, 0.20);
    return geo(iCurl, mCurl, rCurl, pCurl, tNotUp);
  }

  /**
   * THANK YOU  — Thumb + Index + Middle extended.
   *                Ring + Pinky curled.
   *
   *  Nearest neighbour: Hospital (thumb folded in Hospital, spread here)
   *  Nearest neighbour: Water    (pinky closed here; ring also closed here)
   *  Discriminator:     thumb spread OUT (key difference from Hospital)
   */
  function scoreThankYou(f) {
    const { bends: b, thumb: t } = f;
    const iExt  = fallsBelow(b.i, ET, SS);
    const mExt  = fallsBelow(b.m, ET, SS);
    const rCurl = risesAbove(b.r, CT, SS);
    const pCurl = risesAbove(b.p, CT, SS);
    // Thumb must be spread outward — key discriminator from Hospital
    const tOut  = risesAbove(t.toIdx, PARAMS.THUMB_OUT_MIN, 0.09);
    return geo(iExt, mExt, rCurl, pCurl, tOut);
  }

  /**
   * ILY — I Love You (ASL ILY handshape)
   *   Thumb + Index + Pinky extended; Middle + Ring curled.
   *
   *  Nearest neighbour: No     (No has no thumb extended)
   *  Nearest neighbour: Thank you (Thank you has no pinky extended)
   *  Discriminator:     thumb OUT + pinky EXTENDED + middle/ring CURLED
   */
  function scoreILY(f) {
    const { bends: b, thumb: t } = f;
    const iExt  = fallsBelow(b.i, ET, SS);        // index up
    const pExt  = fallsBelow(b.p, ET, SS);        // pinky up — diff from Thank you
    const mCurl = risesAbove(b.m, CT, SS);        // middle curled
    const rCurl = risesAbove(b.r, CT, SS);        // ring curled
    const tOut  = risesAbove(t.toIdx, PARAMS.THUMB_OUT_MIN, 0.09); // thumb spread — diff from No
    return geo(iExt, pExt, mCurl, rCurl, tOut);
  }

  /**
   * BAD — Thumbs DOWN.
   *   Four fingers curled into fist, thumb pointing BELOW wrist level.
   *
   *  Nearest neighbour: Yes (same fist, but thumb UP in Yes)
   *  Nearest neighbour: Emergency (fist, but thumb neutral in Emergency)
   *  Discriminator:     thumbUpward < -0.35 (thumb significantly below wrist)
   */
  function scoreBad(f) {
    const { bends: b, thumb: t } = f;
    const iCurl = risesAbove(b.i, CT, SS);
    const mCurl = risesAbove(b.m, CT, SS);
    const rCurl = risesAbove(b.r, CT, SS);
    const pCurl = risesAbove(b.p, CT, SS);
    // Key: thumb tip is BELOW wrist (negative upward value)
    // fallsBelow(t.upward, -0.35, 0.18) → 1 when t.upward < -0.35 (thumb pointing down)
    const tDown = fallsBelow(t.upward, -0.35, 0.18);
    return geo(iCurl, mCurl, rCurl, pCurl, tDown);
  }

  /**
   * ONE — Index finger only extended upward; all others closed.
   *
   *  Nearest neighbour: Hospital (Hospital has middle also extended)
   *  Nearest neighbour: No (No has pinky also extended)
   *  Discriminator:     ONLY index extended — middle, ring, pinky ALL curled
   */
  function scoreOne(f) {
    const { bends: b, thumb: t } = f;
    const iExt  = fallsBelow(b.i, ET, SS);        // index up
    const mCurl = risesAbove(b.m, CT, SS);        // middle curled — diff from Hospital
    const rCurl = risesAbove(b.r, CT, SS);        // ring curled
    const pCurl = risesAbove(b.p, CT, SS);        // pinky curled — diff from No
    const tIn   = fallsBelow(t.toIdx, 0.37, 0.08); // thumb not spread out
    return geo(iExt, mCurl, rCurl, pCurl, tIn);
  }


  /**
   * CALL AMBULANCE — Phone/Y handshape.
   * Thumb and pinky extended; index, middle and ring curled.
   * This is static enough to read safely and is separated from ILY by requiring
   * the index finger to be curled.
   */
  function scoreCallAmbulance(f) {
    const { bends: b, thumb: t } = f;
    const iCurl = risesAbove(b.i, CT + 4, SS);
    const mCurl = risesAbove(b.m, CT + 4, SS);
    const rCurl = risesAbove(b.r, CT + 4, SS);
    const pExt  = fallsBelow(b.p, ET, SS);
    const tOut  = risesAbove(t.toIdx, 0.46, 0.08);
    const tNotUp = fallsBelow(t.upward, 0.70, 0.25);
    return geo(iCurl, mCurl, rCurl, pExt, tOut, tNotUp);
  }

  /**
   * FOOD / EAT — One-hand pinch.
   * Thumb is close to the index/middle fingertips, with the hand partly curled.
   * It is intentionally strict so it does not steal "Emergency" or "One".
   */
  function scoreFoodEat(f) {
    const { bends: b, lm, scale } = f;
    const thumbIndex  = dist3D(lm[4], lm[8])  / scale;
    const thumbMiddle = dist3D(lm[4], lm[12]) / scale;
    const pinchIndex  = fallsBelow(thumbIndex, 0.30, 0.07);
    const pinchMiddle = fallsBelow(thumbMiddle, 0.36, 0.08);
    const iBent = risesAbove(b.i, 42, 10);
    const mBent = risesAbove(b.m, 42, 10);
    const rNotExtended = risesAbove(b.r, 42, 12);
    const pNotExtended = risesAbove(b.p, 42, 12);
    return geo(pinchIndex, pinchMiddle, iBent, mBent, rNotExtended, pNotExtended);
  }

  /**
   * MORE — All fingertips pinched toward the thumb.
   * This is stricter than Food/Eat because ring and pinky tips must also come in.
   */
  function scoreMore(f) {
    const { bends: b, lm, scale } = f;
    const ti = dist3D(lm[4], lm[8])  / scale;
    const tm = dist3D(lm[4], lm[12]) / scale;
    const tr = dist3D(lm[4], lm[16]) / scale;
    const tp = dist3D(lm[4], lm[20]) / scale;

    const closeI = fallsBelow(ti, 0.30, 0.07);
    const closeM = fallsBelow(tm, 0.34, 0.08);
    const closeR = fallsBelow(tr, 0.42, 0.09);
    const closeP = fallsBelow(tp, 0.48, 0.10);

    const allBent = geo(
      risesAbove(b.i, 36, 12),
      risesAbove(b.m, 36, 12),
      risesAbove(b.r, 36, 12),
      risesAbove(b.p, 36, 12)
    );

    return geo(closeI, closeM, closeR, closeP, allBent);
  }


  /* ── Scorer registry ──────────────────────────────────────────── */
  const SCORERS = {
    'Hello':     scoreHello,
    'Yes':       scoreYes,
    'No':        scoreNo,
    'Help':      scoreHelp,
    'Water':     scoreWater,
    'Hospital':  scoreHospital,
    'Emergency': scoreEmergency,
    'Thank you': scoreThankYou,
    'ILY':       scoreILY,
    'Bad':       scoreBad,
    'One':       scoreOne,
    'Call Ambulance': scoreCallAmbulance,
    'Food / Eat': scoreFoodEat,
    'More':       scoreMore,
  };

  /* ════════════════════════════════════════════════════════════════
     §8  MAIN CLASSIFIER
     ════════════════════════════════════════════════════════════════ */

  /**
   * classify(landmarks)
   *
   * Returns:
   *   { gesture, confidence, scores, isUnknown }
   *
   *   gesture    — winning sign name, or null if ambiguous/unknown
   *   confidence — final adjusted confidence 0-1
   *   scores     — { signName: rawScore } for all signs (useful for debug)
   *   isUnknown  — true when hand IS present but no sign is recognised
   *
   * Confidence penalty logic:
   *   1. Raw score from the dedicated scorer (geometric-mean based).
   *   2. Separation margin: if the top sign beats #2 by less than
   *      MIN_SEPARATION, confidence is reduced proportionally.
   *      This prevents near-ties from being reported with high confidence.
   *   3. If final confidence < MIN_CONFIDENCE → isUnknown = true.
   */
  function classify(landmarks) {
    if (!landmarks || landmarks.length < 21) {
      return { gesture: null, confidence: 0, scores: {}, isUnknown: false };
    }

    const feat   = extractFeatures(landmarks);
    const scores = {};

    // Score every gesture
    for (const [name, scorer] of Object.entries(SCORERS)) {
      scores[name] = Math.max(0, Math.min(1, scorer(feat)));
    }

    // Find top-1 and top-2
    let best = null, bestScore = 0, secondScore = 0;
    for (const [name, score] of Object.entries(scores)) {
      if (score > bestScore) {
        secondScore = bestScore;
        bestScore   = score;
        best        = name;
      } else if (score > secondScore) {
        secondScore = score;
      }
    }

    // Apply separation penalty:
    //   if top and #2 are too close, the classifier is uncertain
    const separation    = bestScore - secondScore;
    const sepFactor     = clamp01(separation / PARAMS.MIN_SEPARATION);
    const confidence    = bestScore * (0.60 + 0.40 * sepFactor); // max 40% penalty

    if (confidence < PARAMS.MIN_CONFIDENCE) {
      return { gesture: null, confidence, scores, isUnknown: true };
    }

    return {
      gesture:   best,
      confidence: Math.round(confidence * 100) / 100,
      scores,
      isUnknown: false,
    };
  }

  /* ════════════════════════════════════════════════════════════════
     §9  EXPONENTIALLY-WEIGHTED SMOOTHER
     ════════════════════════════════════════════════════════════════

     WHY EXPONENTIAL WEIGHTING INSTEAD OF SIMPLE VOTING:
     ─────────────────────────────────────────────────────
     Simple voting (count frames with same gesture):
       • Treats a frame from 1 second ago the same as the latest frame.
       • A gesture held for many old frames can block a new gesture.

     Exponential weighting:
       • Each older frame contributes EXP_DECAY × previous weight.
       • Recent frames dominate naturally.
       • Transitions are smooth: when a gesture changes, the old vote
         decays quickly while the new one builds up.
       • Confidence accumulates quickly for consistently-held gestures
         but collapses immediately when the hand is removed.

     HOW TO READ fillRatio (for the progress bar):
       • 0.0 — nothing detected yet
       • 0.5 — half-way to confirmation
       • 1.0 — confirmed (or about to confirm)
     ════════════════════════════════════════════════════════════════ */

  const _buf = [];        // Rolling buffer of { gesture, confidence }
  let _lastConfirmed   = null;
  let _lastConfirmedAt = 0;
  // Prevents re-confirming the SAME gesture while the hand stays in frame.
  // Cleared only when the hand disappears (resetSmoother) or a different sign takes over.
  let _waitForReset    = false;

  /**
   * smooth(classifyResult)
   *
   * Input:  result from classify()
   * Output: {
   *   gesture   — confirmed gesture name, or null
   *   confidence — weighted confidence (same scale as classify output)
   *   fillRatio  — 0-1, how close we are to confirmation (for progress bar)
   *   isNew      — true only on the first confirmation of a new gesture
   *                (or after COOLDOWN_MS for the same gesture)
   *   candidate  — the leading candidate name even before confirmation
   *                (use to show "Detecting: X" in the UI)
   * }
   */
  function smooth(classifyResult) {
    // Push current frame into the rolling buffer
    _buf.push({
      gesture:    classifyResult.gesture,
      confidence: classifyResult.confidence,
    });
    if (_buf.length > PARAMS.BUFFER_FRAMES) _buf.shift();

    const n = _buf.length;

    // ── Compute exponentially-weighted vote per gesture ────────
    const wScores   = {};   // gesture → Σ(weight × confidence)
    let   totalW    = 0;

    for (let i = 0; i < n; i++) {
      const age  = n - 1 - i;                        // 0 = most recent frame
      const w    = Math.pow(PARAMS.EXP_DECAY, age);
      const { gesture, confidence } = _buf[i];
      totalW += w;
      if (gesture) {
        wScores[gesture] = (wScores[gesture] || 0) + w * confidence;
      }
    }

    // Normalise: weighted vote ratio for each gesture
    let topGesture = null, topScore = 0, secondWScore = 0;
    for (const [g, s] of Object.entries(wScores)) {
      const ns = s / totalW;
      if (ns > topScore) {
        secondWScore = topScore;
        topScore     = ns;
        topGesture   = g;
      } else if (ns > secondWScore) {
        secondWScore = ns;
      }
    }

    // fillRatio: proportion of MIN_CONFIRM threshold reached (for progress bar)
    const fillRatio = clamp01(topScore / PARAMS.MIN_CONFIRM);

    // Need enough frames in the buffer before we accept a confirmation
    const enoughFrames = n >= PARAMS.BUFFER_FRAMES * PARAMS.MIN_BUFFER_FILL;

    // ── Confirmation check ─────────────────────────────────────
    if (enoughFrames && topScore >= PARAMS.MIN_CONFIRM && topGesture !== null) {
      const now = Date.now();

      // If a DIFFERENT gesture has taken over while we were waiting for a reset,
      // clear the wait so the new gesture can confirm immediately.
      if (_waitForReset && topGesture !== _lastConfirmed) {
        _waitForReset = false;
      }

      // isNew fires only when:
      //   (a) the hand was lowered/reset since last confirmation, OR
      //   (b) this is a genuinely different gesture
      // It does NOT fire just because COOLDOWN_MS elapsed while the hand
      // keeps holding the same pose — that was the source of the repeat-speak bug.
      const isNew = !_waitForReset && (
        topGesture !== _lastConfirmed ||
        (now - _lastConfirmedAt) > PARAMS.COOLDOWN_MS
      );

      if (isNew) {
        _lastConfirmed   = topGesture;
        _lastConfirmedAt = now;
        _waitForReset    = true;   // require hand removal before re-confirming same sign
      }

      return {
        gesture:    topGesture,
        confidence: clamp01(topScore),
        fillRatio:  1,
        isNew,
        candidate:  topGesture,
      };
    }

    // Not yet confirmed — return progress info
    const candidate = topScore > 0.20 ? topGesture : null;
    return {
      gesture:    null,
      confidence: topScore,
      fillRatio,
      isNew:      false,
      candidate,
    };
  }

  /**
   * Reset the smoother buffer.
   * Call when camera stops or hand disappears for > 0.5 s.
   * Does NOT reset the cooldown timer — the same gesture must
   * still wait COOLDOWN_MS before re-firing.
   */
  function resetSmoother() {
    _buf.length   = 0;
    _waitForReset = false;  // hand left frame — allow re-confirmation on next appearance
    // _lastConfirmed / _lastConfirmedAt preserved (cooldown still applies across appearances)
  }

  /* ════════════════════════════════════════════════════════════════
     §10  LIGHTING CHECK
     — Samples the central 30 % of the canvas for perceived brightness.
     — Returns 0 (black) – 255 (white).  Below ~55 = low light warning.
     ════════════════════════════════════════════════════════════════ */
  function checkLighting(ctx, canvasW, canvasH) {
    try {
      const x = Math.floor(canvasW * 0.35);
      const y = Math.floor(canvasH * 0.35);
      const w = Math.floor(canvasW * 0.30);
      const h = Math.floor(canvasH * 0.30);
      if (w < 1 || h < 1) return 128;
      const data = ctx.getImageData(x, y, w, h).data;
      let sum = 0;
      for (let i = 0; i < data.length; i += 4) {
        // ITU-R BT.601 perceived luminance
        sum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
      }
      return sum / (data.length / 4);
    } catch (_) {
      return 128; // cross-origin canvas or WebGL → assume OK
    }
  }

  /* ════════════════════════════════════════════════════════════════
     §11  LEGACY COMPAT — getFingerStates()
     ════════════════════════════════════════════════════════════════ */

  /**
   * Returns a 0-1 extension ratio for each finger.
   * Kept for backward compatibility with any external code that
   * imported this function from v2. Internally unused in v3.
   * Uses the new angle-based computation.
   */
  function getFingerStates(lm) {
    const bends = allFingerBends(lm);
    const toExt = (b) => clamp01(fallsBelow(b, ET, SS));
    return {
      T: clamp01(risesAbove(dist3D(lm[4], lm[5]) / handScale(lm), 0.35, 0.10)),
      I: toExt(bends.i),
      M: toExt(bends.m),
      R: toExt(bends.r),
      P: toExt(bends.p),
    };
  }

  /* ════════════════════════════════════════════════════════════════
     §12  TRAINED MODEL INTEGRATION HOOK
     ════════════════════════════════════════════════════════════════

     STATIC GESTURES (this classifier handles these):
     ────────────────────────────────────────────────
     All 8 current gestures are STATIC poses — they are defined by
     a single snapshot of hand shape, not movement.
     Rule-based geometry works well for these.

     DYNAMIC GESTURES (require a trained sequence model):
     ─────────────────────────────────────────────────────
     These require tracking how the hand MOVES over time:
       • ASL letter J  (index traces a hook)
       • ASL letter Z  (index traces a Z in the air)
       • "Please"       (flat hand circles on chest — motion)
       • "Sorry"        (fist circles on chest — motion)
       • "More"         (fingertips pinch and tap together)
       • "Come here"    (curling finger motion)
       • Full sentences with transitional handshapes

     HOW TO CONNECT A TRAINED TENSORFLOW.JS MODEL:
     ─────────────────────────────────────────────
     Step 1:  Train a model on a landmark dataset (e.g., ASL dataset
              from Kaggle, or record your own with the Rafiq app).
              Input shape: (sequence_length × 63) for dynamic,
                           (1 × 63) for static.

     Step 2:  Load the model in the page:
              const model = await tf.loadLayersModel('/models/sign_model/model.json');

     Step 3:  Replace the classify() call in sign_language.php:

              // OLD (rule-based):
              const frameResult = SLClassifier.classify(landmarks);

              // NEW (trained model — static):
              const frameResult = await connectTrainedModel(landmarks, model);

              // NEW (trained model — dynamic, pass a landmark buffer):
              const frameResult = await connectTrainedModelDynamic(landmarkBuffer, model);

     Step 4:  Implement the connector:

              async function connectTrainedModel(landmarks, model) {
                // Flatten 21 landmarks × (x, y, z) = 63 floats
                const flat  = landmarks.flatMap(lm => [lm.x, lm.y, lm.z || 0]);
                const input = tf.tensor2d([flat]);                    // shape [1, 63]
                const probs = await model.predict(input).data();      // shape [N_CLASSES]
                input.dispose();

                const CLASS_NAMES = ['Hello','Yes','No','Help','Water',
                                     'Hospital','Emergency','Thank you'];
                const maxIdx  = probs.indexOf(Math.max(...probs));
                const confidence = probs[maxIdx];

                return {
                  gesture:   confidence > 0.55 ? CLASS_NAMES[maxIdx] : null,
                  confidence,
                  scores:    Object.fromEntries(CLASS_NAMES.map((n,i) => [n, probs[i]])),
                  isUnknown: confidence <= 0.55,
                };
              }

     ONNX Runtime Web alternative:
     ──────────────────────────────
              const session = await ort.InferenceSession.create('/models/sign.onnx');

              async function connectTrainedModel(landmarks, session) {
                const flat  = new Float32Array(landmarks.flatMap(lm => [lm.x, lm.y, lm.z || 0]));
                const input = new ort.Tensor('float32', flat, [1, 63]);
                const out   = await session.run({ input });
                const probs = out.output.data;
                // ... same indexing as TF.js above
              }

     LSTM for DYNAMIC gestures:
     ───────────────────────────
              // Maintain a rolling buffer of N frames of landmarks (e.g. N=30)
              // Each frame contributes 63 floats → input shape [1, 30, 63]
              const seq   = landmarkBuffer.map(lm => lm.flatMap(p => [p.x, p.y, p.z||0]));
              const input = tf.tensor3d([seq]);   // [1, 30, 63]
              const probs = await lstmModel.predict(input).data();
     ════════════════════════════════════════════════════════════════ */

  /* ════════════════════════════════════════════════════════════════
     §13  PUBLIC API EXPORT
     ════════════════════════════════════════════════════════════════ */
  global.SLClassifier = {
    // Meta
    SIGNS,
    PARAMS,

    // Core
    classify,
    smooth,
    resetSmoother,

    // Utilities
    checkLighting,
    extractFeatures,  // expose for debugging / dev tools
    getFingerStates,  // backward compat with v2

    // Named constants (kept for any external code that read them from v2)
    BUFFER_FRAMES:   PARAMS.BUFFER_FRAMES,
    MIN_CONFIDENCE:  PARAMS.MIN_CONFIDENCE,
    COOLDOWN_MS:     PARAMS.COOLDOWN_MS,
  };

})(window);
