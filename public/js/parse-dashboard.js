/**
 * parse-dashboard.js — Pure ESM module.
 *
 * Extracts and normalizes a dashboard object from various input formats:
 *   1. Pure JSON strings (e.g. JSON.stringify(layout) from the project load endpoint)
 *   2. Markdown code blocks (e.g. AI chat responses: ```json {...} ```)
 *   3. Embedded JSON objects inside larger text (falls back to brace matching)
 *
 * This module is the single source of truth for dashboard JSON parsing and is
 * unit-tested in tests/js/parse-dashboard.test.js.
 */

/**
 * Extract the JSON payload from content (string). Returns the parsed object or null.
 */
export function extractJson(content) {
    if (typeof content !== 'string') return null;

    // Strategy 1: try direct JSON parse first (handles pure JSON strings)
    try {
        return JSON.parse(content);
    } catch (e) {
        // Not valid JSON — fall through to pattern matching
    }

    // Strategy 2: markdown-wrapped JSON code block
    const jsonMatch = content.match(/```(?:json)?\s*([\s\S]*?)\s*```/);
    let jsonStr = jsonMatch ? jsonMatch[1] : null;

    // Strategy 3: find embedded JSON object by balanced braces.
    // Search from most-to-least likely starting key so we never match a
    // property value like {"theme":...} that sits INSIDE the real object.
    if (!jsonStr) {
        let brace = content.indexOf('{"title"');
        if (brace < 0) brace = content.indexOf('{"cards"');
        if (brace < 0) brace = content.indexOf('{"dashboard"');
        if (brace < 0) brace = content.indexOf('{"theme"');
        if (brace >= 0) {
            let depth = 0;
            let end = brace;
            for (let i = brace; i < content.length; i++) {
                if (content[i] === '{') depth++;
                if (content[i] === '}') {
                    depth--;
                    if (depth === 0) { end = i + 1; break; }
                }
            }
            jsonStr = content.substring(brace, end);
        }
    }

    if (!jsonStr) return null;

    try {
        return JSON.parse(jsonStr);
    } catch (e) {
        return null;
    }
}

/**
 * Normalize card defaults (id, width, height, per-type overrides).
 * Returns a new array of cards.
 */
export function normalizeCards(cards) {
    if (!Array.isArray(cards)) return [];

    return cards.map((c, i) => {
        const card = { ...c };
        if (!card.id) card.id = 'card-' + i;
        if (!card.w) card.w = card.width || card.colspan || card.colSpan || 3;
        if (!card.h) card.h = card.height || card.rowspan || card.rowSpan ||
            (card.type === 'divider' ? 1
                : card.type === 'subheader' ? 1
                : card.type === 'header' || card.type === 'title' || card.type === 'section' ? 2
                : card.type === 'kpi' || card.type === 'stat' ? 3
                : 6);
        if (card.type === 'divider') { card.w = 4; card.h = 1; }
        if (card.type === 'title') { card.w = 4; card.h = card.h || 2; }
        if (card.type === 'header') { card.w = 4; card.h = card.h || 1; }
        if (card.type === 'subheader') { card.w = 4; card.h = card.h || 1; }
        if (card.type === 'section') { card.w = 4; card.h = card.h || 2; }
        return card;
    });
}

/**
 * Parse dashboard content and return a normalized dashboard object,
 * or null if no valid dashboard JSON could be extracted.
 */
export function parseDashboard(content) {
    let json = extractJson(content);
    if (!json) return null;

    // Unwrap {dashboard: {...}} wrapper (AI chat response format)
    if (json.dashboard && typeof json.dashboard === 'object') json = json.dashboard;

    // Must have a non-empty cards array
    if (!json.cards || !json.cards.length) return null;

    json.cards = normalizeCards(json.cards);
    if (!json.title) json.title = 'Dashboard';

    return json;
}
