/**
 * parse-dashboard.test.js — Unit tests for the pure dashboard JSON parser.
 *
 * Run with: node --test tests/js/parse-dashboard.test.js
 * (uses Node's built-in test runner — no extra dependencies)
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractJson, normalizeCards, parseDashboard } from '../../public/js/parse-dashboard.js';

// --- extractJson ----------------------------------------------------------

test('extractJson: parses pure JSON string (title-first — the original bug)', () => {
    const json = extractJson('{"title":"Overview","theme":"dark","cards":[]}');
    assert.ok(json, 'should return an object');
    assert.equal(json.title, 'Overview');
    assert.equal(json.theme, 'dark');
});

test('extractJson: parses pure JSON starting with cards', () => {
    const json = extractJson('{"cards":[],"title":"X"}');
    assert.ok(json);
    assert.deepEqual(json.cards, []);
});

test('extractJson: parses JSON wrapped in markdown code block', () => {
    const content = 'Here you go:\n```json\n{"title":"AI Dash","cards":[]}\n```\nEnjoy!';
    const json = extractJson(content);
    assert.ok(json);
    assert.equal(json.title, 'AI Dash');
});

test('extractJson: finds embedded JSON inside prose (title first)', () => {
    const content = 'Result: {"title":"Embedded","theme":"dark","cards":[]} — done.';
    const json = extractJson(content);
    assert.ok(json);
    assert.equal(json.title, 'Embedded');
});

test('extractJson: does NOT capture theme fragment when title precedes it', () => {
    // Regression test for the original bug: the old code matched {"theme"...}
    // at the wrong offset and captured only {"theme":"dark"}.
    const content = '{"title":"Beneficiary Performance","theme":"dark","cards":[{"id":"kpi-1","type":"kpi"}]}';
    const json = extractJson(content);
    assert.ok(json);
    assert.equal(json.title, 'Beneficiary Performance');
    assert.equal(json.theme, 'dark');
    assert.equal(json.cards.length, 1);
});

test('extractJson: returns null for malformed input', () => {
    assert.equal(extractJson('not json at all'), null);
    assert.equal(extractJson(''), null);
    assert.equal(extractJson('{"broken": '), null);
});

test('extractJson: returns null for non-string input', () => {
    assert.equal(extractJson(null), null);
    assert.equal(extractJson(undefined), null);
    assert.equal(extractJson(42), null);
    assert.equal(extractJson({}), null);
});

// --- normalizeCards -------------------------------------------------------

test('normalizeCards: assigns missing ids and width/height defaults', () => {
    const cards = normalizeCards([
        { type: 'kpi', title: 'KPI' },
        { type: 'line', title: 'Chart' },
    ]);

    assert.equal(cards[0].id, 'card-0');
    assert.equal(cards[0].w, 3);
    assert.equal(cards[0].h, 3);
    assert.equal(cards[1].id, 'card-1');
    assert.equal(cards[1].h, 6);
});

test('normalizeCards: applies per-type width/height overrides', () => {
    const cards = normalizeCards([
        { type: 'divider' },
        { type: 'title', title: 'T' },
        { type: 'header', title: 'H' },
        { type: 'subheader', title: 'S' },
        { type: 'section', title: 'Sec' },
    ]);

    assert.equal(cards[0].w, 4);
    assert.equal(cards[0].h, 1);
    assert.equal(cards[1].w, 4);
    assert.equal(cards[1].h, 2);
    // Matches original builder behavior: header defaults to h=2 from the
    // formula (header|title|section gives 2), so the h||1 override never fires.
    assert.equal(cards[2].h, 2);
    assert.equal(cards[3].h, 1);
    assert.equal(cards[4].h, 2);
});

test('normalizeCards: preserves explicit w/h and col/row', () => {
    const cards = normalizeCards([
        { id: 'kpi-1', type: 'kpi', w: 2, h: 4, col: 0, row: 1 },
    ]);

    assert.equal(cards[0].w, 2);
    assert.equal(cards[0].h, 4);
    assert.equal(cards[0].col, 0);
    assert.equal(cards[0].row, 1);
});

test('normalizeCards: supports legacy width/colspan/rowspan aliases', () => {
    const cards = normalizeCards([
        { type: 'bar', width: 1, rowspan: 5 },
    ]);

    assert.equal(cards[0].w, 1);
    assert.equal(cards[0].h, 5);
});

test('normalizeCards: returns [] for non-array input', () => {
    assert.deepEqual(normalizeCards(undefined), []);
    assert.deepEqual(normalizeCards(null), []);
    assert.deepEqual(normalizeCards('nope'), []);
});

// --- parseDashboard -------------------------------------------------------

test('parseDashboard: unwraps {dashboard: {...}} wrapper (AI chat format)', () => {
    const content = JSON.stringify({
        dashboard: { title: 'Wrapped', theme: 'dark', cards: [{ type: 'kpi' }] },
    });
    const dash = parseDashboard(content);
    assert.ok(dash);
    assert.equal(dash.title, 'Wrapped');
    assert.equal(dash.cards.length, 1);
});

test('parseDashboard: returns null when no cards array present', () => {
    assert.equal(parseDashboard('{"title":"No cards"}'), null);
    assert.equal(parseDashboard('{"cards":[]}'), null);
    assert.equal(parseDashboard('{"theme":"dark"}'), null);
});

test('parseDashboard: returns null for garbage content', () => {
    assert.equal(parseDashboard('this is not json'), null);
    assert.equal(parseDashboard(''), null);
    assert.equal(parseDashboard(null), null);
});

test('parseDashboard: defaults title to "Dashboard" when missing', () => {
    const dash = parseDashboard('{"cards":[{"type":"kpi"}]}');
    assert.ok(dash);
    assert.equal(dash.title, 'Dashboard');
});

test('parseDashboard: full pipeline — pure layout string (the project-load path)', () => {
    const layout = {
        title: 'Beneficiary Performance Overview',
        theme: 'dark',
        cards: [
            { id: 'title-1', type: 'title', title: 'Overview', w: 4, h: 2, col: 0, row: 0 },
            { id: 'kpi-1', type: 'kpi', title: 'Active', w: 1, h: 3, col: 0, row: 2 },
        ],
    };

    const dash = parseDashboard(JSON.stringify(layout));
    assert.ok(dash);
    assert.equal(dash.title, 'Beneficiary Performance Overview');
    assert.equal(dash.theme, 'dark');
    assert.equal(dash.cards.length, 2);
    assert.equal(dash.cards[1].id, 'kpi-1');
    assert.equal(dash.cards[1].h, 3);
});

test('parseDashboard: full pipeline — markdown-wrapped AI response', () => {
    const content = 'Here is your dashboard:\n```json\n{"title":"AI Build","cards":[{"type":"line","title":"Trend"}]}\n```';
    const dash = parseDashboard(content);
    assert.ok(dash);
    assert.equal(dash.title, 'AI Build');
    assert.equal(dash.cards[0].h, 6); // default height for line charts
});
