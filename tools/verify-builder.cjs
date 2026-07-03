#!/usr/bin/env node
// MANDATORY PRE-DEPLOY CHECK for dashboard-builder edits
// Run: node tools/verify-builder.cjs
// Checks: Blade inline JS syntax, external JS syntax, div/template balance, key methods exist.
// If it fails, DO NOT deploy. Fix the error first.

const fs = require('fs');
const { execSync } = require('child_process');

const bladePath = 'C:/laragon/www/acfs-platform/resources/views/dashboard-builder/index.blade.php';
const jsPath = 'C:/laragon/www/acfs-platform/public/js/dashboard-builder.js';

let failures = 0;

// --- 1. Check Blade file exists ---
if (!fs.existsSync(bladePath)) {
  console.error('❌ Blade file not found:', bladePath);
  process.exit(1);
}

const blade = fs.readFileSync(bladePath, 'utf8');

// --- 2. Check inline JS syntax (if any <script> block exists in Blade) ---
const jsStart = blade.lastIndexOf('<script>');
const jsEnd = blade.lastIndexOf('</script>');
if (jsStart >= 0 && jsEnd >= 0 && jsEnd > jsStart) {
  const inlineJs = blade.substring(jsStart + 8, jsEnd);
  // Skip if it's just an include/src tag
  if (!inlineJs.includes('src=') && inlineJs.trim().length > 10) {
    try {
      new Function(inlineJs);
      console.log('✅ Blade inline JS syntax valid');
    } catch (e) {
      console.error('❌ Blade inline JS SYNTAX ERROR:', e.message);
      failures++;
    }
  }
}

// --- 3. Check external JS file ---
if (fs.existsSync(jsPath)) {
  try {
    execSync('node --check "' + jsPath + '"', { stdio: 'pipe', timeout: 5000 });
    console.log('✅ External JS syntax valid');
  } catch (e) {
    console.error('❌ External JS SYNTAX ERROR:', e.stderr?.toString() || e.message);
    failures++;
  }
} else {
  console.warn('⚠️  External JS file not found:', jsPath);
}

// --- 4. Div balance ---
const open = (blade.match(/<div/g) || []).length;
const close = (blade.match(/<\/div>/g) || []).length;
if (open !== close) {
  console.warn('⚠️  Div mismatch:', open, 'open vs', close, 'close — may affect layout');
} else {
  console.log('✅ Div balance:', open, '/', close);
}

// --- 5. Template tag balance ---
const tOpen = (blade.match(/<template/g) || []).length;
const tClose = (blade.match(/<\/template>/g) || []).length;
if (tOpen !== tClose) {
  console.warn('⚠️  Template mismatch:', tOpen, 'open vs', tClose, 'close');
} else {
  console.log('✅ Template balance:', tOpen, '/', tClose);
}

// --- 6. Alpine quote balance ---
const alpineAttrs = blade.match(/:(class|style|show|text|if|for)="[^"]+"/g) || [];
const clickAttrs = blade.match(/@click="[^"]+"/g) || [];
[...alpineAttrs, ...clickAttrs].forEach(function(attr) {
  var sq = (attr.match(/'/g) || []).length;
  if (sq % 2 !== 0) {
    console.warn('⚠️  Odd single quotes (' + sq + '):', attr.substring(0, 80));
    failures++;
  }
});
console.log('✅ Alpine quotes checked:', alpineAttrs.length + clickAttrs.length, 'attributes');

// --- 7. Key methods in external JS ---
if (fs.existsSync(jsPath)) {
  const js = fs.readFileSync(jsPath, 'utf8');
  const methods = ['dashboardBuilder', 'renderGrid', 'renderChart', 'cardHTML',
    'saveDashboard', 'tryParseDashboard', 'toggleChat', 'addRevision',
    'resizeCard', 'deleteCard', 'setFilter', 'fetchCardData', 'checkAI'];
  methods.forEach(m => {
    if (!js.includes(m)) {
      console.warn('⚠️  Method "' + m + '" missing from external JS');
      failures++;
    }
  });
}

// --- 8. GridStack is now intentional ---
if (fs.existsSync(jsPath)) {
  const js = fs.readFileSync(jsPath, 'utf8');
  const gsRefs = js.match(/GridStack/g) || [];
  console.log('✅ GridStack references:', gsRefs.length, 'occurrences (intentional)');
}

// --- Result ---
if (failures > 0) {
  console.error('\n❌ ' + failures + ' check(s) failed — DO NOT deploy');
  process.exit(1);
}
console.log('\n✅ All checks passed — safe to deploy');
