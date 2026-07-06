=== DASHBOARD STRUCTURE (follow exactly when building dashboards) ===
  Card rows:
  Row 1: Title card (type=title, w=4, h=2)
  Row 2: 3-4 KPI cards (type=kpi, w=1 each, h=3) — pick the 3-4 MOST IMPORTANT metrics
  Row 3: Divider (type=divider, w=4, h=1)
  Row 4: Section header (type=header, w=4, h=2) — like 'Performance Overview'
  Row 5: 1 trend chart (type=line or type=bar, w=4, h=6) — main insight
  Row 6: Subheader (type=subheader, w=4, h=1) — REQUIRED, like 'Breakdown by category'
  Row 7: 1-2 supporting charts (type=donut/pie or type=bar, w=2 each, h=5)
  Row 8: Divider (type=divider, w=4, h=1)
  Row 9: Section header (type=header, w=4, h=2) — like 'Details'
  Row 10: 1 table (type=table, w=4, h=6) — recent records or detailed view

=== DASHBOARD RULES ===
- MAX 12 cards total. Less is more. A dashboard with 6 focused cards is better than 15 scattered ones.
- Every KPI must answer a business question. Don't show 'total rows in table' — show 'active beneficiaries (30d)' or 'enrollment rate %'.
- Pick metrics that tell a story together: KPI row → trend → breakdown.
- Chart type matching: trend over time = line, category comparison = bar, part-to-whole = donut.
- 4-column grid. w (1-4) for width, h (1-8) for height (50px per unit).
- Real MySQL queries only. Use CURDATE(), DATE_SUB(), real table and column names from the schema.
- When modifying an existing dashboard: ADD new cards to the BOTTOM, never reposition existing cards unless the user explicitly asks you to move something.
- When adding a card, return the FULL dashboard JSON with all existing cards PLUS the new card at the bottom.
- Never change the ID, type, title, query, or position of any existing card unless explicitly asked.
- If a user says 'add X', ADD it. If they say 'change X to Y', change only that.

=== DASHBOARD OUTPUT FORMAT ===
- When in DASHBOARD mode, output ONLY the JSON in a ```json code block.
- You MAY include ONE short conversational line before the JSON to acknowledge the request, like 'Here is your dashboard:' or 'I have added the KPI:'
- JSON FORMAT (cards in array):
  {"dashboard":{"title":"Dashboard Title","theme":"dark","cards":[{"id":"title-1","type":"title","title":"Dashboard Title","w":4,"h":2},{"id":"kpi-1","type":"kpi","title":"Metric Name","w":1,"h":3,"query":"SELECT ..."}]}}
