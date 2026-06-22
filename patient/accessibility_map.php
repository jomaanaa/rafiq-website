<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Rafiq | Accessibility Map</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Leaflet Map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

  <!-- Font Awesome if your nav uses icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --primary: #404066;
      --primary2: #5f62b3;
      --dark: #2b2c41;
      --bg: #f6f8ff;
      --card: #ffffff;
      --text: #23263a;
      --muted: #70778d;
      --border: #e7eaf3;
      --chip: #f1f4ff;
      --chipBorder: #dde3ff;
      --success: #1fa971;
      --danger: #e05252;
      --star: #ffc22a;
      --shadow: 0 12px 30px rgba(43, 44, 65, 0.12);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    .accessibility-page {
      padding: 24px;
      max-width: 1450px;
      margin: auto;
    }

    .page-hero {
      background: linear-gradient(135deg, #404066, #54558c, #6e6bff);
      color: white;
      border-radius: 26px;
      padding: 28px;
      box-shadow: var(--shadow);
      margin-bottom: 18px;
      position: relative;
      overflow: hidden;
    }

    .page-hero::before,
    .page-hero::after {
      content: "";
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,0.08);
    }

    .page-hero::before {
      width: 180px;
      height: 180px;
      right: -40px;
      top: -60px;
    }

    .page-hero::after {
      width: 120px;
      height: 120px;
      left: -30px;
      bottom: -40px;
    }

    .page-hero h1 {
      margin: 0 0 8px;
      font-size: 30px;
      position: relative;
      z-index: 2;
    }

    .page-hero p {
      margin: 0;
      opacity: 0.9;
      line-height: 1.6;
      position: relative;
      z-index: 2;
    }

    .hero-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 14px;
      position: relative;
      z-index: 2;
    }

    .hero-badges span {
      padding: 7px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      font-size: 13px;
      font-weight: bold;
    }

    .tabs {
      background: var(--chip);
      border: 1px solid var(--chipBorder);
      padding: 6px;
      border-radius: 16px;
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
    }

    .tab-btn {
      flex: 1;
      border: none;
      background: transparent;
      padding: 13px;
      border-radius: 12px;
      cursor: pointer;
      color: var(--muted);
      font-weight: bold;
      font-size: 14px;
    }

    .tab-btn.active {
      background: var(--primary);
      color: white;
      box-shadow: 0 6px 16px rgba(64,64,102,0.25);
    }

    .tab-page {
      display: none;
    }

    .tab-page.active {
      display: block;
    }

    .controls-grid {
      display: grid;
      grid-template-columns: 1.5fr 0.6fr;
      gap: 16px;
      margin-bottom: 16px;
    }

    .card {
      background: var(--card);
      border-radius: 22px;
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      padding: 16px;
    }

    .search-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .search-row input {
      flex: 1;
      min-width: 240px;
      padding: 13px 15px;
      border-radius: 14px;
      border: 1px solid var(--border);
      outline: none;
      font-weight: 600;
    }

    .search-row input:focus,
    select:focus,
    textarea:focus {
      border-color: #9ea4ff;
      box-shadow: 0 0 0 4px rgba(110,107,255,0.12);
    }

    button,
    select,
    input,
    textarea {
      font-family: inherit;
    }

    .main-btn,
    .soft-btn,
    .clear-btn,
    .review-btn,
    .submit-btn {
      border: none;
      border-radius: 14px;
      padding: 12px 16px;
      cursor: pointer;
      font-weight: bold;
    }

    .main-btn {
      background: var(--primary);
      color: white;
    }

    .soft-btn {
      background: #eef2ff;
      color: var(--primary);
      border: 1px solid var(--chipBorder);
    }

    .clear-btn {
      background: #fff0f0;
      color: var(--danger);
      border: 1px solid #ffc9c9;
      margin-top: 10px;
    }

    .filters {
      margin-top: 16px;
      padding-top: 14px;
      border-top: 1px solid var(--border);
    }

    .label {
      font-size: 12px;
      color: var(--muted);
      font-weight: bold;
      margin: 14px 0 8px;
      text-transform: uppercase;
    }

    .pill-wrap {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .pill {
      border: 1px solid #dbe0ee;
      background: white;
      color: var(--dark);
      border-radius: 999px;
      padding: 9px 13px;
      cursor: pointer;
      font-size: 13px;
      font-weight: bold;
    }

    .pill.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    .sort-card label {
      display: block;
      font-size: 12px;
      color: var(--muted);
      font-weight: bold;
      margin-bottom: 8px;
      text-transform: uppercase;
    }

    .sort-card select {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 12px;
      font-weight: bold;
      outline: none;
    }

    .status-text {
      color: var(--muted);
      font-size: 13px;
      margin-bottom: 0;
      line-height: 1.5;
    }

    .map-grid {
      display: grid;
      grid-template-columns: 1.35fr 0.75fr;
      gap: 16px;
      align-items: start;
    }

    .map-card,
    .places-card,
    .community-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: 22px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .section-head {
      padding: 16px 18px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .section-head h2 {
      margin: 0;
      font-size: 18px;
      color: var(--dark);
    }

    .section-head p {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 13px;
    }

    #accessMap {
      width: 100%;
      height: 640px;
    }

    .places-card {
      height: 705px;
      display: flex;
      flex-direction: column;
    }

    .places-list,
    .community-list {
      padding: 16px;
      overflow: auto;
    }

    .places-list {
      flex: 1;
    }

    .place-card,
    .review-card {
      border: 1px solid #ebedf5;
      border-radius: 18px;
      background: white;
      padding: 15px;
      margin-bottom: 12px;
    }

    .place-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(43,44,65,0.08);
    }

    .place-top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
    }

    .place-name {
      margin: 0;
      font-size: 15px;
      font-weight: bold;
      color: var(--dark);
    }

    .place-address {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.5;
    }

    .rating {
      color: var(--star);
      font-weight: bold;
      white-space: nowrap;
      text-align: right;
    }

    .badge-row {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 10px;
    }

    .badge {
      background: var(--chip);
      border: 1px solid var(--chipBorder);
      color: var(--primary2);
      border-radius: 999px;
      padding: 5px 9px;
      font-size: 11px;
      font-weight: bold;
    }

    .score-row {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }

    .score-box {
      flex: 1;
      background: #fafbff;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 8px;
      text-align: center;
    }

    .score-box strong {
      display: block;
      font-size: 14px;
      color: var(--dark);
    }

    .score-box span {
      font-size: 10px;
      color: var(--muted);
      font-weight: bold;
    }

    .review-btn {
      width: 100%;
      margin-top: 12px;
      background: linear-gradient(135deg, var(--primary), #6e6bff);
      color: white;
    }

    .empty {
      padding: 40px 16px;
      text-align: center;
      color: var(--muted);
      line-height: 1.6;
    }

    .community-card {
      margin-top: 16px;
    }

    .community-actions {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .community-actions select {
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 11px;
      font-weight: bold;
      outline: none;
    }

    .comment-bubble {
      background: var(--chip);
      border-radius: 5px 14px 14px 14px;
      padding: 9px 11px;
      font-size: 12px;
      line-height: 1.5;
      margin-top: 8px;
    }

    .marker-pin {
      width: 32px;
      height: 32px;
      border-radius: 50% 50% 50% 0;
      transform: rotate(-45deg);
      background: var(--primary);
      border: 3px solid white;
      box-shadow: 0 6px 12px rgba(0,0,0,0.25);
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .marker-pin span {
      transform: rotate(45deg);
      font-size: 14px;
    }

    .marker-user {
      background: var(--success);
    }

    .modal-bg {
      position: fixed;
      inset: 0;
      background: rgba(22,24,40,0.45);
      backdrop-filter: blur(8px);
      z-index: 9999;
      display: none;
      justify-content: center;
      align-items: flex-end;
      padding: 20px;
    }

    .modal-bg.show {
      display: flex;
    }

    .review-modal {
      width: min(620px, 100%);
      max-height: 92vh;
      overflow: auto;
      background: var(--bg);
      border-radius: 26px;
      padding: 22px;
      position: relative;
      box-shadow: 0 30px 70px rgba(0,0,0,0.28);
    }

    .close-modal {
      position: absolute;
      right: 16px;
      top: 14px;
      width: 34px;
      height: 34px;
      border: none;
      border-radius: 50%;
      background: white;
      font-size: 24px;
      cursor: pointer;
    }

    .review-modal h2 {
      margin: 0 0 4px;
      color: var(--dark);
    }

    .review-modal .selected-name {
      margin: 0 0 16px;
      color: var(--muted);
      font-size: 13px;
    }

    .form-block {
      margin: 16px 0;
    }

    .form-block label {
      display: block;
      color: var(--muted);
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 8px;
    }

    .stars {
      display: flex;
      gap: 3px;
    }

    .star {
      background: transparent;
      border: none;
      font-size: 32px;
      cursor: pointer;
      color: #d6d9e6;
    }

    .star.active {
      color: var(--star);
    }

    .feature-grid {
      display: grid;
      gap: 9px;
    }

    .feature-row {
      display: grid;
      grid-template-columns: 1fr auto auto;
      align-items: center;
      gap: 8px;
      background: white;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 10px 12px;
      font-weight: bold;
    }

    .yesno {
      border: 1px solid #dbe0ee;
      border-radius: 999px;
      background: white;
      padding: 7px 10px;
      cursor: pointer;
      font-weight: bold;
    }

    .yesno.yes.active {
      background: #e8fff4;
      color: var(--success);
      border-color: #b6f0d8;
    }

    .yesno.no.active {
      background: #fff0f0;
      color: var(--danger);
      border-color: #ffc6c6;
    }

    textarea {
      width: 100%;
      resize: vertical;
      min-height: 90px;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 13px;
      outline: none;
    }

    .review-message {
      display: none;
      padding: 11px;
      border-radius: 12px;
      margin-bottom: 12px;
      font-weight: bold;
      font-size: 13px;
    }

    .review-message.ok {
      display: block;
      background: #e8fff4;
      color: var(--success);
      border: 1px solid #b6f0d8;
    }

    .review-message.err {
      display: block;
      background: #fff0f0;
      color: var(--danger);
      border: 1px solid #ffc6c6;
    }

    .submit-btn {
      width: 100%;
      background: linear-gradient(135deg, var(--primary), #6e6bff);
      color: white;
      padding: 15px;
      font-size: 15px;
    }

    @media (max-width: 1000px) {
      .controls-grid,
      .map-grid {
        grid-template-columns: 1fr;
      }

      .places-card {
        height: auto;
        max-height: 650px;
      }

      #accessMap {
        height: 520px;
      }
    }

    @media (max-width: 600px) {
      .accessibility-page {
        padding: 14px;
      }

      .page-hero h1 {
        font-size: 25px;
      }

      .search-row {
        display: block;
      }

      .search-row input,
      .search-row button {
        width: 100%;
        margin-top: 8px;
      }

      .section-head {
        flex-direction: column;
        align-items: flex-start;
      }

      .community-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
      }

      .feature-row {
        grid-template-columns: 1fr 55px 55px;
      }
    }
  </style>
</head>

<body>

<?php
$navPath = __DIR__ . '/../general/nav_patient.php';
if (file_exists($navPath)) {
    include $navPath;
}
?>

<main class="accessibility-page">

  <section class="page-hero">
    <h1>Find Accessible Places</h1>
    <p>Discover nearby places, filter by accessibility features, and read community reviews before you go.</p>
    <div class="hero-badges">
      <span>Wheelchair</span>
      <span>Elevator</span>
      <span>Ramp</span>
      <span>Toilet</span>
      <span>Parking</span>
    </div>
  </section>

  <section class="tabs">
    <button class="tab-btn active" data-tab="explore">Explore</button>
    <button class="tab-btn" data-tab="community">Community</button>
  </section>

  <section id="explore" class="tab-page active">

    <div class="controls-grid">

      <div class="card">
        <div class="search-row">
          <input id="searchInput" type="text" placeholder="Search a place or area in Egypt...">
          <button id="searchBtn" class="main-btn">Search</button>
          <button id="locateBtn" class="soft-btn">📍 My Location</button>
        </div>

        <div class="filters">
          <p class="label">Place Type</p>
          <div id="typeFilters" class="pill-wrap"></div>

          <p class="label">Accessibility</p>
          <div id="accessFilters" class="pill-wrap"></div>

          <button id="clearFilters" class="clear-btn">Clear Filters</button>
        </div>
      </div>

      <div class="card sort-card">
        <label for="sortMode">Sort Results</label>
        <select id="sortMode">
          <option value="nearest">Nearest</option>
          <option value="name_asc">Name A → Z</option>
          <option value="name_desc">Name Z → A</option>
          <option value="accessibility">Most Accessible</option>
          <option value="rating">Top Community Rating</option>
        </select>
        <p id="statusText" class="status-text">Ready to search nearby places.</p>
      </div>

    </div>

    <div class="map-grid">

      <section class="map-card">
        <div class="section-head">
          <div>
            <h2>Map View</h2>
            <p id="mapSub">Places shown on the map</p>
          </div>
        </div>
        <div id="accessMap"></div>
      </section>

      <aside class="places-card">
        <div class="section-head">
          <div>
            <h2>Places</h2>
            <p id="listSub">Search results</p>
          </div>
        </div>
        <div id="placesList" class="places-list"></div>
      </aside>

    </div>

  </section>

  <section id="community" class="tab-page">

    <div class="community-card">
      <div class="section-head">
        <div>
          <h2>Community Reviews</h2>
          <p id="communitySub">Places reviewed by Rafiq users</p>
        </div>

        <div class="community-actions">
          <select id="communitySort">
            <option value="rating">Top Rated</option>
            <option value="accessibility">Most Accessible</option>
            <option value="name_asc">A → Z</option>
          </select>
          <button id="refreshCommunity" class="soft-btn">Refresh</button>
        </div>
      </div>

      <div id="communityList" class="community-list"></div>
    </div>

  </section>

</main>

<div id="reviewModal" class="modal-bg">
  <div class="review-modal">
    <button id="closeModal" class="close-modal">×</button>

    <h2>Leave a Review</h2>
    <p id="selectedPlaceName" class="selected-name">Selected place</p>

    <form id="reviewForm">

      <div class="form-block">
        <label>Overall Rating</label>
        <div id="stars" class="stars"></div>
      </div>

      <div class="form-block">
        <label>Accessibility Features</label>
        <div id="reviewFeatures" class="feature-grid"></div>
      </div>

      <div class="form-block">
        <label>Comment</label>
        <textarea id="reviewComment" placeholder="Write your accessibility experience here..."></textarea>
      </div>

      <div id="reviewMessage" class="review-message"></div>

      <button id="submitReview" type="submit" class="submit-btn">Submit Review</button>
    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(() => {
  const API_FILE = '../general/accessibility_api.php';

  const egypt = {
    lat: 30.0444,
    lng: 31.2357
  };

  const state = {
    map: null,
    center: egypt,
    user: null,
    userMarker: null,
    markers: [],
    allPlaces: [],
    filteredPlaces: [],
    reviewedPlaces: [],
    selectedType: 0,
    selectedFilters: new Set(),
    sortMode: 'nearest',
    communitySort: 'rating',
    selectedPlace: null,
    starRating: 0,
    featureVotes: {
      wheelchair: null,
      elevator: null,
      ramp: null,
      toilet: null,
      parking: null
    }
  };

  const placeTypes = [
  {
    label: 'All',
    tags: []
  },
  {
    label: 'Hospitals',
    tags: [
      ['amenity', 'hospital'],
      ['amenity', 'clinic'],
      ['amenity', 'pharmacy'],
      ['amenity', 'dentist'],
      ['amenity', 'doctors']
    ]
  },
  {
    label: 'Malls',
    tags: [
      ['shop', 'mall'],
      ['shop', 'department_store'],
      ['shop', 'supermarket'],
      ['landuse', 'retail']
    ]
  },
  {
    label: 'Museums',
    tags: [
      ['tourism', 'museum'],
      ['tourism', 'gallery'],
      ['tourism', 'attraction'],
      ['historic', 'monument']
    ]
  },
  {
    label: 'Restaurants',
    tags: [
      ['amenity', 'restaurant'],
      ['amenity', 'cafe'],
      ['amenity', 'fast_food'],
      ['amenity', 'food_court'],
      ['amenity', 'ice_cream'],
      ['amenity', 'juice_bar'],
      ['amenity', 'bakery']
    ]
  },
  {
    label: 'Parks',
    tags: [
      ['leisure', 'park'],
      ['leisure', 'garden'],
      ['leisure', 'playground'],
      ['leisure', 'nature_reserve'],
      ['landuse', 'recreation_ground']
    ]
  },
  {
    label: 'Transit',
    tags: [
      ['public_transport', 'station'],
      ['railway', 'station'],
      ['railway', 'halt'],
      ['amenity', 'bus_station'],
      ['highway', 'bus_stop']
    ]
  },
  {
    label: 'Hotels',
    tags: [
      ['tourism', 'hotel'],
      ['tourism', 'hostel'],
      ['tourism', 'guest_house'],
      ['tourism', 'apartment']
    ]
  }
];

  const accessFeatures = [
    ['wheelchair', 'Wheelchair'],
    ['elevator', 'Elevator'],
    ['ramp', 'Ramp'],
    ['toilet', 'Toilet'],
    ['parking', 'Parking']
  ];

  const $ = id => document.getElementById(id);

  function escapeHtml(value = '') {
    return String(value).replace(/[&<>'"]/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[char]));
  }

  function setStatus(text) {
    $('statusText').textContent = text;
  }

  function init() {
    initMap();
    renderFilterButtons();
    renderStars();
    renderFeatureButtons();
    bindEvents();
    locateAndFetch();
  }

  function initMap() {
    state.map = L.map('accessMap').setView([egypt.lat, egypt.lng], 13);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      maxZoom: 20,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(state.map);
  }

  function bindEvents() {
    document.querySelectorAll('.tab-btn').forEach(button => {
      button.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-page').forEach(page => page.classList.remove('active'));

        button.classList.add('active');
        $(button.dataset.tab).classList.add('active');

        if (button.dataset.tab === 'explore') {
          setTimeout(() => state.map.invalidateSize(), 200);
        }

        if (button.dataset.tab === 'community') {
          fetchReviewedPlaces();
        }
      });
    });

    $('searchBtn').addEventListener('click', searchLocation);

    $('searchInput').addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        searchLocation();
      }
    });

    $('locateBtn').addEventListener('click', locateAndFetch);

    $('sortMode').addEventListener('change', event => {
      state.sortMode = event.target.value;
      applyFilters();
    });

    $('communitySort').addEventListener('change', event => {
      state.communitySort = event.target.value;
      renderCommunity();
    });

    $('refreshCommunity').addEventListener('click', fetchReviewedPlaces);

    $('clearFilters').addEventListener('click', () => {
      state.selectedType = 0;
      state.selectedFilters.clear();
      renderFilterButtons();
      fetchPlaces(state.center);
    });

    $('closeModal').addEventListener('click', closeReviewModal);

    $('reviewModal').addEventListener('click', event => {
      if (event.target.id === 'reviewModal') {
        closeReviewModal();
      }
    });

    $('reviewForm').addEventListener('submit', submitReview);
  }

  function renderFilterButtons() {
    $('typeFilters').innerHTML = placeTypes.map((type, index) => `
      <button class="pill ${state.selectedType === index ? 'active' : ''}" data-type="${index}">
        ${type.label}
      </button>
    `).join('');

    document.querySelectorAll('[data-type]').forEach(button => {
      button.addEventListener('click', () => {
        state.selectedType = Number(button.dataset.type);
        renderFilterButtons();
        fetchPlaces(state.center);
      });
    });

    $('accessFilters').innerHTML = accessFeatures.map(([key, label]) => `
      <button class="pill ${state.selectedFilters.has(key) ? 'active' : ''}" data-filter="${key}">
        ${label}
      </button>
    `).join('');

    document.querySelectorAll('[data-filter]').forEach(button => {
      button.addEventListener('click', () => {
        const key = button.dataset.filter;

        if (state.selectedFilters.has(key)) {
          state.selectedFilters.delete(key);
        } else {
          state.selectedFilters.add(key);
        }

        renderFilterButtons();
        applyFilters();
      });
    });
  }

  function locateAndFetch() {
    setStatus('Getting your location...');

    if (!navigator.geolocation) {
      fetchPlaces(egypt);
      return;
    }

    navigator.geolocation.getCurrentPosition(
      position => {
        state.user = {
          lat: position.coords.latitude,
          lng: position.coords.longitude
        };

        state.center = state.user;

        state.map.setView([state.user.lat, state.user.lng], 14);
        addUserMarker();
        fetchPlaces(state.user);
      },
      () => {
        setStatus('Location permission denied. Showing Cairo as default.');
        fetchPlaces(egypt);
      },
      {
        enableHighAccuracy: true,
        timeout: 9000,
        maximumAge: 60000
      }
    );
  }

  async function searchLocation() {
    const query = $('searchInput').value.trim();

    if (!query) {
      fetchPlaces(state.user || egypt);
      return;
    }

    setStatus('Searching location...');

    try {
      const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1&countrycodes=eg&accept-language=en`;
      const response = await fetch(url);
      const data = await response.json();

      if (!data.length) {
        setStatus('No location found. Try another search.');
        return;
      }

      const center = {
        lat: parseFloat(data[0].lat),
        lng: parseFloat(data[0].lon)
      };

      state.center = center;
      state.map.setView([center.lat, center.lng], 15);
      fetchPlaces(center);

    } catch (error) {
      setStatus('Search failed. Please try again.');
    }
  }

  async function fetchPlaces(center) {
    try {
      setStatus('Loading nearby places...');

      const overpassPlaces = await fetchOverpassPlaces(center.lat, center.lng, 5000, state.selectedType);
      const features = await getPlaceFeatures(overpassPlaces.map(place => place.id));

      state.allPlaces = overpassPlaces.map(place => {
        return mergeFeatures(place, features[place.id]);
      });

      applyFilters();
      setStatus(`Loaded ${state.allPlaces.length} places nearby.`);

    } catch (error) {
      state.allPlaces = [];
      state.filteredPlaces = [];
      renderPlaces();
      renderMarkers();
      setStatus(error.message || 'Could not load places.');
    }
  }

  async function fetchOverpassPlaces(lat, lng, radius, typeIndex) {
    const query = buildOverpassQuery(lat, lng, radius, typeIndex);
    const endpoints = [
      'https://overpass-api.de/api/interpreter',
      'https://overpass.kumi.systems/api/interpreter'
    ];

    let lastError = '';

    for (const endpoint of endpoints) {
      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: `data=${encodeURIComponent(query)}`
        });

        if (response.ok) {
          const json = await response.json();
          return (json.elements || [])
            .map(element => parseOverpassElement(element, lat, lng))
            .filter(Boolean);
        }

        lastError = `HTTP ${response.status}`;
      } catch (error) {
        lastError = error.message;
      }
    }

    throw new Error(`Could not reach map data service. ${lastError}`);
  }

  function buildOverpassQuery(lat, lng, radius, typeIndex) {
    const around = `around:${radius},${lat},${lng}`;
    const type = placeTypes[typeIndex];

    if (!type.tags.length) {
      return `
[out:json][timeout:45];
(
  node["amenity"~"^(hospital|clinic|pharmacy|dentist|doctors|restaurant|cafe|fast_food|food_court|ice_cream|juice_bar|bakery|university|school|bank|atm|place_of_worship|theatre|cinema|library|community_centre|bus_station|parking|toilets)$"](${around});
  way["amenity"~"^(hospital|clinic|pharmacy|restaurant|cafe|fast_food|food_court|university|school|bank|place_of_worship|theatre|cinema|library|bus_station|parking|toilets)$"](${around});
  relation["amenity"~"^(hospital|clinic|pharmacy|restaurant|cafe|fast_food|food_court|university|school|bank|place_of_worship|theatre|cinema|library|bus_station)$"](${around});

  node["tourism"~"^(museum|hotel|hostel|guest_house|attraction|gallery|apartment)$"](${around});
  way["tourism"~"^(museum|hotel|hostel|guest_house|attraction|gallery|apartment)$"](${around});
  relation["tourism"~"^(museum|hotel|hostel|guest_house|attraction|gallery|apartment)$"](${around});

  node["leisure"~"^(park|garden|playground|sports_centre|fitness_centre|swimming_pool|nature_reserve)$"](${around});
  way["leisure"~"^(park|garden|playground|sports_centre|fitness_centre|swimming_pool|nature_reserve)$"](${around});
  relation["leisure"~"^(park|garden|playground|sports_centre|fitness_centre|swimming_pool|nature_reserve)$"](${around});

  node["shop"~"^(mall|department_store|supermarket|convenience|bakery)$"](${around});
  way["shop"~"^(mall|department_store|supermarket)$"](${around});
  relation["shop"~"^(mall|department_store|supermarket)$"](${around});

  node["public_transport"="station"](${around});
  way["public_transport"="station"](${around});
  relation["public_transport"="station"](${around});

  node["railway"~"^(station|halt)$"](${around});
  way["railway"~"^(station|halt)$"](${around});
  relation["railway"~"^(station|halt)$"](${around});
);
out center 200;
`;
    }

    let query = '[out:json][timeout:45];\n(\n';

    type.tags.forEach(([key, value]) => {
      query += `  node["${key}"="${value}"](${around});\n`;
      query += `  way["${key}"="${value}"](${around});\n`;
      query += `  relation["${key}"="${value}"](${around});\n`;
    });

    query += ');\nout center 200;';
    return query;
  }

  function parseOverpassElement(element, userLat, userLng) {
    const tags = element.tags || {};

    let lat = element.lat;
    let lng = element.lon;

    if ((element.type === 'way' || element.type === 'relation') && element.center) {
      lat = element.center.lat;
      lng = element.center.lon;
    }

    if (!lat || !lng) {
      return null;
    }

    const name = (
      tags['name:en'] ||
      tags.name ||
      tags['name:ar'] ||
      tags['brand:en'] ||
      tags.brand ||
      ''
    ).trim();

    if (!name) {
      return null;
    }

    const addressParts = [];

    if (tags['addr:housenumber'] && tags['addr:street']) {
      addressParts.push(`${tags['addr:housenumber']} ${tags['addr:street']}`);
    } else if (tags['addr:street']) {
      addressParts.push(tags['addr:street']);
    }

    if (tags['addr:suburb']) addressParts.push(tags['addr:suburb']);
    if (tags['addr:city']) addressParts.push(tags['addr:city']);

    const wheelchair = tags.wheelchair === 'yes' || tags.wheelchair === 'limited';
    const elevator = tags.elevator === 'yes' || tags.highway === 'elevator';
    const ramp = tags.ramp === 'yes' || tags['ramp:wheelchair'] === 'yes' || tags.kerb === 'lowered' || tags.kerb === 'flush';
    const toilet = tags['toilets:wheelchair'] === 'yes' || tags['wheelchair:toilet'] === 'yes' || (tags.amenity === 'toilets' && wheelchair);
    const parking = tags['parking:condition'] === 'disabled' || tags['capacity:disabled'] !== undefined || (tags.amenity === 'parking' && wheelchair);

    return {
      id: `${element.type}_${element.id}`,
      name: name,
      address: addressParts.length ? addressParts.join(', ') : 'Egypt',
      type: resolvePlaceType(tags),
      latitude: parseFloat(lat),
      longitude: parseFloat(lng),
      wheelchair: wheelchair,
      elevator: elevator,
      ramp: ramp,
      toilet: toilet,
      parking: parking,
      rating: 0,
      review_count: 0,
      comments: [],
      distance_km: haversine(userLat, userLng, parseFloat(lat), parseFloat(lng))
    };
  }

  function resolvePlaceType(tags) {
    if (tags.amenity) return tags.amenity.replaceAll('_', ' ');
    if (tags.shop) return tags.shop.replaceAll('_', ' ');
    if (tags.tourism) return tags.tourism.replaceAll('_', ' ');
    if (tags.leisure) return tags.leisure.replaceAll('_', ' ');
    if (tags.railway) return 'railway ' + tags.railway.replaceAll('_', ' ');
    if (tags.public_transport) return 'public transport';
    if (tags.historic) return tags.historic.replaceAll('_', ' ');
    return 'Place';
  }

  async function getPlaceFeatures(osmIds) {
    if (!osmIds.length) return {};

    const response = await fetch(`${API_FILE}?action=place_features`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        osm_ids: osmIds
      })
    });

    return await response.json();
  }

  async function getReviewedPlaces() {
    const response = await fetch(`${API_FILE}?action=reviewed_places`);
    return await response.json();
  }

  async function sendReview(payload) {
    const response = await fetch(`${API_FILE}?action=submit_review`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    return await response.json();
  }

  function mergeFeatures(place, overlay) {
    if (!overlay) return place;

    return {
      ...place,
      wheelchair: place.wheelchair || overlay.wheelchair,
      elevator: place.elevator || overlay.elevator,
      ramp: place.ramp || overlay.ramp,
      toilet: place.toilet || overlay.toilet,
      parking: place.parking || overlay.parking,
      rating: overlay.rating || 0,
      review_count: overlay.review_count || 0
    };
  }

  function applyFilters() {
    let list = [...state.allPlaces];

    state.selectedFilters.forEach(filter => {
      list = list.filter(place => place[filter]);
    });

    list.sort((a, b) => {
      if (state.sortMode === 'nearest') {
        return (a.distance_km || 9999) - (b.distance_km || 9999);
      }

      if (state.sortMode === 'name_asc') {
        return a.name.localeCompare(b.name);
      }

      if (state.sortMode === 'name_desc') {
        return b.name.localeCompare(a.name);
      }

      if (state.sortMode === 'accessibility') {
        return accessibilityScore(b) - accessibilityScore(a);
      }

      if (state.sortMode === 'rating') {
        return (b.rating || 0) - (a.rating || 0);
      }

      return 0;
    });

    state.filteredPlaces = list;

    renderPlaces();
    renderMarkers();
  }

  function renderPlaces() {
    $('listSub').textContent = `${state.filteredPlaces.length} results`;

    if (!state.filteredPlaces.length) {
      $('placesList').innerHTML = `
        <div class="empty">
          No places match these filters.<br>
          Try clearing filters or searching another area.
        </div>
      `;
      return;
    }

    $('placesList').innerHTML = state.filteredPlaces.map(place => renderPlaceCard(place)).join('');

    document.querySelectorAll('[data-review]').forEach(button => {
      button.addEventListener('click', () => {
        const place = state.filteredPlaces.find(item => item.id === button.dataset.review);
        openReviewModal(place);
      });
    });

    document.querySelectorAll('[data-focus]').forEach(button => {
      button.addEventListener('click', () => {
        const place = state.filteredPlaces.find(item => item.id === button.dataset.focus);
        focusPlace(place);
      });
    });
  }

  function renderPlaceCard(place) {
    return `
      <article class="place-card" id="place-${cssSafe(place.id)}">
        <div class="place-top">
          <div>
            <h3 class="place-name">${escapeHtml(place.name)}</h3>
            <p class="place-address">${escapeHtml(place.address)}</p>
          </div>
          <div class="rating">
            ${stars(place.rating)}
            <br>
            <small>${place.rating ? Number(place.rating).toFixed(1) : '—'}</small>
          </div>
        </div>

        <div class="badge-row">
          <span class="badge">${escapeHtml(place.type)}</span>
          <span class="badge">${formatKm(place.distance_km)}</span>
          <span class="badge">${place.review_count || 0} reviews</span>
        </div>

        <div class="badge-row">
          ${featureBadges(place)}
        </div>

        <div class="score-row">
          <div class="score-box">
            <strong>${accessibilityScore(place)}/5</strong>
            <span>Accessibility</span>
          </div>
          <div class="score-box">
            <strong>${place.rating ? Number(place.rating).toFixed(1) : '—'}</strong>
            <span>Avg Rating</span>
          </div>
          <div class="score-box">
            <strong>${blendedScore(place).toFixed(1)}</strong>
            <span>Blended</span>
          </div>
        </div>

        <button class="review-btn" data-review="${escapeHtml(place.id)}">Leave Review</button>
        <button class="soft-btn" style="width:100%;margin-top:8px;" data-focus="${escapeHtml(place.id)}">Show on Map</button>
      </article>
    `;
  }

  function renderMarkers() {
    clearMarkers();
    addUserMarker();

    state.filteredPlaces.forEach(place => {
      const marker = L.marker([place.latitude, place.longitude], {
        icon: markerIcon('📍')
      }).addTo(state.map);

      marker.bindPopup(`
        <strong>${escapeHtml(place.name)}</strong><br>
        ${escapeHtml(place.type)}<br>
        ${featureText(place)}<br>
        <button onclick="window.openRafiqReview('${place.id}')">Review</button>
      `);

      marker.on('click', () => {
        scrollToPlace(place.id);
      });

      state.markers.push(marker);
    });

    $('mapSub').textContent = `${state.filteredPlaces.length} places shown`;
  }

  function clearMarkers() {
    state.markers.forEach(marker => state.map.removeLayer(marker));
    state.markers = [];
  }

  function addUserMarker() {
    if (!state.user) return;

    if (state.userMarker) {
      state.map.removeLayer(state.userMarker);
    }

    state.userMarker = L.marker([state.user.lat, state.user.lng], {
      icon: markerIcon('📍', 'marker-user')
    }).addTo(state.map).bindPopup('Your location');
  }

  function markerIcon(emoji, extraClass = '') {
    return L.divIcon({
      className: '',
      html: `<div class="marker-pin ${extraClass}"><span>${emoji}</span></div>`,
      iconSize: [32, 32],
      iconAnchor: [16, 32]
    });
  }

  function focusPlace(place) {
    if (!place) return;

    state.map.setView([place.latitude, place.longitude], 17);
    scrollToPlace(place.id);
  }

  function scrollToPlace(id) {
    const element = document.getElementById(`place-${cssSafe(id)}`);
    if (element) {
      element.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });
    }
  }

  async function fetchReviewedPlaces() {
    $('communityList').innerHTML = `<div class="empty">Loading community reviews...</div>`;

    try {
      const data = await getReviewedPlaces();

      if (!Array.isArray(data)) {
        $('communityList').innerHTML = `<div class="empty">Could not load reviews.</div>`;
        return;
      }

      state.reviewedPlaces = data;
      renderCommunity();

    } catch (error) {
      $('communityList').innerHTML = `<div class="empty">Could not load community reviews.</div>`;
    }
  }

  function renderCommunity() {
    let list = [...state.reviewedPlaces];

    list.sort((a, b) => {
      if (state.communitySort === 'rating') {
        return blendedScore(b) - blendedScore(a);
      }

      if (state.communitySort === 'accessibility') {
        return accessibilityScore(b) - accessibilityScore(a);
      }

      return String(a.name).localeCompare(String(b.name));
    });

    $('communitySub').textContent = `${list.length} reviewed places`;

    if (!list.length) {
      $('communityList').innerHTML = `
        <div class="empty">
          No community reviews yet.<br>
          Be the first to review a place from Explore.
        </div>
      `;
      return;
    }

    $('communityList').innerHTML = list.map(place => `
      <article class="review-card">
        <div class="place-top">
          <div>
            <h3 class="place-name">${escapeHtml(place.name)}</h3>
            <p class="place-address">${escapeHtml(place.address || '')}</p>
          </div>
          <div class="rating">
            ${stars(place.rating)}
            <br>
            <small>${Number(place.rating || 0).toFixed(1)}</small>
          </div>
        </div>

        <div class="badge-row">
          <span class="badge">${escapeHtml(place.type || 'Place')}</span>
          <span class="badge">${accessibilityScore(place)}/5 features</span>
          <span class="badge">${place.review_count || 0} reviews</span>
        </div>

        <div class="badge-row">
          ${featureBadges(place)}
        </div>

       ${(place.comments || []).map(comment => `
  <div class="comment-bubble">${escapeHtml(cleanComment(comment))}</div>
`).join('')}
      </article>
    `).join('');
  }

  window.openRafiqReview = function(id) {
    const place = state.filteredPlaces.find(item => item.id === id);
    openReviewModal(place);
  };

  function openReviewModal(place) {
    if (!place) return;

    state.selectedPlace = place;
    state.starRating = 0;

    state.featureVotes = {
      wheelchair: place.wheelchair ? true : null,
      elevator: place.elevator ? true : null,
      ramp: place.ramp ? true : null,
      toilet: place.toilet ? true : null,
      parking: place.parking ? true : null
    };

    $('selectedPlaceName').textContent = place.name;
    $('reviewComment').value = '';
    $('reviewMessage').className = 'review-message';
    $('reviewMessage').textContent = '';

    renderStars();
    renderFeatureButtons();

    $('reviewModal').classList.add('show');
  }

  function closeReviewModal() {
    $('reviewModal').classList.remove('show');
  }

  function renderStars() {
    $('stars').innerHTML = [1, 2, 3, 4, 5].map(number => `
      <button type="button" class="star ${number <= state.starRating ? 'active' : ''}" data-star="${number}">★</button>
    `).join('');

    document.querySelectorAll('[data-star]').forEach(button => {
      button.addEventListener('click', () => {
        state.starRating = Number(button.dataset.star);
        renderStars();
      });
    });
  }

  function renderFeatureButtons() {
    $('reviewFeatures').innerHTML = accessFeatures.map(([key, label]) => `
      <div class="feature-row">
        <span>${label}</span>
        <button type="button" class="yesno yes ${state.featureVotes[key] === true ? 'active' : ''}" data-vote="${key}" data-value="yes">Yes</button>
        <button type="button" class="yesno no ${state.featureVotes[key] === false ? 'active' : ''}" data-vote="${key}" data-value="no">No</button>
      </div>
    `).join('');

    document.querySelectorAll('[data-vote]').forEach(button => {
      button.addEventListener('click', () => {
        const key = button.dataset.vote;
        state.featureVotes[key] = button.dataset.value === 'yes';
        renderFeatureButtons();
      });
    });
  }

  async function submitReview(event) {
    event.preventDefault();

    const place = state.selectedPlace;
    if (!place) return;

    const hasFeatureVote = Object.values(state.featureVotes).some(value => value !== null);

    if (!hasFeatureVote && state.starRating === 0) {
      showReviewMessage('Please add a star rating or choose at least one accessibility feature.', false);
      return;
    }

    $('submitReview').disabled = true;
    $('submitReview').textContent = 'Submitting...';

    const payload = {
      osm_id: place.id,
      name: place.name,
      type: place.type,
      address: place.address,
      latitude: place.latitude,
      longitude: place.longitude,
      wheelchair: state.featureVotes.wheelchair,
      elevator: state.featureVotes.elevator,
      ramp: state.featureVotes.ramp,
      toilet: state.featureVotes.toilet,
      parking: state.featureVotes.parking,
      rating: state.starRating || null,
      comment: $('reviewComment').value.trim()
    };

    try {
      const result = await sendReview(payload);

      if (result.success) {
        showReviewMessage('Review submitted successfully.', true);

        const features = await getPlaceFeatures([place.id]);
        const index = state.allPlaces.findIndex(item => item.id === place.id);

        if (index >= 0) {
          state.allPlaces[index] = mergeFeatures(state.allPlaces[index], features[place.id]);
        }

        applyFilters();
        fetchReviewedPlaces();

        setTimeout(closeReviewModal, 900);

      } else {
        showReviewMessage(result.message || 'Could not submit review.', false);
      }

    } catch (error) {
      showReviewMessage('Network error. Please try again.', false);
    }

    $('submitReview').disabled = false;
    $('submitReview').textContent = 'Submit Review';
  }

  function showReviewMessage(message, ok) {
    $('reviewMessage').textContent = message;
    $('reviewMessage').className = ok ? 'review-message ok' : 'review-message err';
  }

  function accessibilityScore(place) {
    return accessFeatures.filter(([key]) => place[key]).length;
  }

  function blendedScore(place) {
    const rating = Number(place.rating || 0);
    const score = accessibilityScore(place);

    if (!rating) return score;

    return (rating * 0.6) + (score * 0.4);
  }

  function featureBadges(place) {
    const badges = accessFeatures
      .filter(([key]) => place[key])
      .map(([key, label]) => `<span class="badge">${label}</span>`)
      .join('');

    return badges || `<span class="badge">No accessibility features recorded</span>`;
  }

  function featureText(place) {
    const text = accessFeatures
      .filter(([key]) => place[key])
      .map(([key, label]) => `${label}`)
      .join(' · ');

    return text || 'No accessibility features recorded';
  }

  function stars(rating) {
    const value = Math.round(Number(rating || 0));
    let output = '';

    for (let i = 1; i <= 5; i++) {
      output += i <= value ? '★' : '☆';
    }

    return output;
  }

  function formatKm(value) {
    if (!Number.isFinite(Number(value))) {
      return '—';
    }

    return `${Number(value).toFixed(2)} km`;
  }

  function cssSafe(value) {
    return String(value).replace(/[^a-zA-Z0-9_-]/g, '_');
  }

  function haversine(lat1, lon1, lat2, lon2) {
    const r = 6371;
    const toRad = value => value * Math.PI / 180;

    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);

    const a =
      Math.sin(dLat / 2) ** 2 +
      Math.cos(toRad(lat1)) *
      Math.cos(toRad(lat2)) *
      Math.sin(dLon / 2) ** 2;

    return r * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }
  function cleanComment(comment) {
  return String(comment || '')
    .replace(/\[osm:[^\]]*\]\s*/gi, '')
    .replace(/\|\s*Not wheelchair accessible\s*/gi, '')
    .replace(/\|\s*Wheelchair accessible\s*/gi, '')
    .trim();
}

  document.addEventListener('DOMContentLoaded', init);
})();
</script>
<?php include '../general/footer.php'; ?>
</body>
</html>