<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║           RAFIQ AI CHATBOT — API KEY CONFIGURATION           ║
 * ╠══════════════════════════════════════════════════════════════╣
 * ║                                                              ║
 * ║  Using GROQ — free, works in Egypt, no credit card needed    ║
 * ║                                                              ║
 * ║  HOW TO GET A FREE GROQ API KEY:                             ║
 * ║  1. Open: https://console.groq.com                           ║
 * ║  2. Sign up with Google or GitHub (free)                     ║
 * ║  3. Go to "API Keys" in the left sidebar                     ║
 * ║  4. Click "Create API Key" → copy it (starts with "gsk_")   ║
 * ║  5. Paste it below in RAFIQ_AI_KEY and save                  ║
 * ║                                                              ║
 * ║  Free limits: 6,000 requests/day — more than enough          ║
 * ║                                                              ║
 * ╚══════════════════════════════════════════════════════════════╝
 */

/* ── YOUR GROQ API KEY ─────────────────────────────────────
 *  Do NOT put the real key here. Store it in:
 *    pgdb/chatbot_secret.php   (this file is git-ignored)
 *
 *  That file should contain exactly one line:
 *    define('RAFIQ_AI_KEY', 'gsk_xxxxxxxxxxxxxxxxxxxxxxxx');
 * ──────────────────────────────────────────────────────── */
$_secret = __DIR__ . '/chatbot_secret.php';
if (file_exists($_secret)) {
    require_once $_secret;
} elseif (!defined('RAFIQ_AI_KEY')) {
    // Fallback: set an empty key so the app doesn't crash;
    // the chatbot will return an auth error instead of a PHP error.
    define('RAFIQ_AI_KEY', '');
}
unset($_secret);

define('RAFIQ_AI_MODEL',  'llama-3.3-70b-versatile');  // free & powerful
define('RAFIQ_AI_TEMP',   0.70);
define('RAFIQ_AI_TOKENS', 512);

define('RAFIQ_SYSTEM_PROMPT', <<<PROMPT
You are Rafiq Assistant, the friendly AI helper for the Rafiq platform — an Egyptian accessibility service that helps people with disabilities book:
- Wheelchair-accessible Drivers
- Doctors (home visits)
- Caregivers (daily living assistance)
- Sign-language Interpreters

Rafiq also has an interactive Map of accessible places in Egypt (hospitals, malls, restaurants, parks, pharmacies, transport hubs) with filters for elevator, ramp, accessible parking, and accessible toilets.

Key facts:
- Bookings: Instant (right now) or Scheduled (future date/time)
- Payment: cash or Visa card
- Rating system for providers after each completed booking
- Driver fare: EGP 8 per km (shown before booking)
- Support email: support@rafiq.eg
- Emergencies in Egypt: call 123 or 16000 (ambulance)

Your rules:
- Be warm, patient, clear, and supportive
- Use short sentences and bullet points for steps
- Keep responses under 200 words unless asked for more
- Answer ANY question — not just Rafiq questions
- For emergencies ALWAYS mention: call 123 or 16000
- Respond in the same language the user writes in (Arabic or English)
- Never invent features that do not exist on the platform
PROMPT);
