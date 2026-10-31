# Brief: Pattern development with several tools

**Context.** Today a pattern development covers one pattern. A real job needs a pattern and core boxes, or a core box alone. The name stays "Pattern development". Each development now has a list of **tools**: a pattern, a core box, a gauge, a fixture. Small foundries must still see one tool, one row and almost no extra screens. Larger ones get per-tool maker, cost, owner and status. All extra depth is per-rule and optional.

**Existing screens to keep (design 11a, 11b, 12a, 14b).** Development list, Start development dialog, development page (stage bar, details, cost line, log), enquiry record "Tooling and pattern" box, enquiry intake part card.

## Decisions made
- Each tool has a **status pill** (Not started, Building, Built, Trial passed, Rework, Not needed) **and** a **mini stage bar** on the development page.
- In **tables and lists** show the pill only. On the **development page** show the mini bar (with the pill).
- The development keeps its one shared stage bar; the per-tool mini bar shows that tool's own progress through Build and Trial.

## What "Create register record" means
A tool row on a development starts as a plan (a label such as "Core box for the housing"). The register is the real record in the system: a Pattern in the pattern register, or a Core box (with its core definition and core requirement) under Engineering. **Create register record** is the row action that turns the planned tool into that real record, pre-filled from the row (label, maker, owner, modified-from), and links the two. Before it, the tool exists only on the development. After it, the row shows a link to the register record. It is needed for core lots, life limits, maintenance and ownership to work. It is available once the part exists (or at Approved for a brand-new part), and a core box becomes Active when the row is marked Built so trial cores can be made. Free-text kinds (gauge, fixture, other) have no register and no such action.

## Screens to design

**1. Development page: Tooling section** (replaces the Pattern, Pattern by and Actual pattern cost fields)
- A panel under the stage bar. One row per tool with: kind icon and label, route tag (Existing, New, Modify), made by and maker, who pays, quoted and actual cost, due date, status pill and mini stage bar.
- Row actions: change status, record cost, Create register record, link to the register record, remove.
- "+ Add tool" with a kind picker. Footer: total quoted against total actual; the existing allowed-in-quote and over/within line stays.
- One-tool state looks almost like today's page. Empty state: "No tools yet" with Add tool.
- Shared tool: a row can link to a tool on another development; it shows that status, carries no cost, marked "Shared with PD-0031".
- Tooling module off: free-text kinds only, no register links.

**2. Stage bar with tool progress**
- Count under the bar: "2 of 3 built" at Build, "1 of 3 passed trial" at Trial.
- Gated move message above the button: "Core box CB-118 is not built yet." Show Warn (move anyway) and Block (button disabled) variants.

**3. Send back dialog** (replaces the reason-only bar)
- Reason plus a tool checklist, failed tools ticked. Unticked tools keep their status; ticked go to Rework. Hide the checklist when there is one tool.

**4. Start development dialog**
- Under the type cards, a **Tools** section: Pattern on by default, "+ Core box", "+ Gauge", "+ Other". Each opens an inline mini-row: route, pick from register or type a label, made by.
- For Replacement and Modification the pattern picker becomes "Tool to replace or modify" and accepts a pattern or core box.
- Also design the entry from a core box page and a pattern page ("Start replacement").

**5. Enquiry record: Tooling and pattern box**
- Today's three options stay as the quick answer for the pattern. Below, a per-part list of planned tools, each with its own route (e.g. "Pattern: Existing P-0412. Core box: New"), pre-filled from feasibility ("2 cores expected") and the part's core requirements. Inline edit, "Add tool".
- Mixed state: existing pattern plus new box opens a development for the box.
- Life warning also covers core boxes: "CB-118: 92% of life, Start replacement".
- Changing the decision after a tool has started: warning "Core box CB-118 is already building and stays", not a silent cancel.

**6. Enquiry intake part card**
- One compact line: "Tooling: pattern + 2 core boxes (edit)", expanding to the screen 5 list. Collapsed by default.

**7. Quote tooling**
- Tooling cost field shows "Sum of tools: X" with a link to adjust. Optional variant: a collapsed tool list on the quote.

**8. Development list**
- "Tools" column with the pill summary ("3 · 2 built"), search matching tool labels, filter "waiting on a core box". Pills only, no bars.

**9. Core box register**
- Owner and custody fields on the core box page, matching the pattern ownership panel. A "Developments" list on pattern and core box pages. Status line "Trial cores allowed" once Built.

**10. Settings**
- One rule: "Tools must be built and trial-passed before a development moves on": Block / Warn / Off, default Warn, beside "When an enquiry opens its development".

## States to cover
One tool, three tools, tool in Rework, gated move (Warn and Block), shared tool, Tooling module off, core box not yet in the register (new part, no product), cancelled development with started tools.
