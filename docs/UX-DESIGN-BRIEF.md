# Claude Design brief: rough flow sketches (round 1)

Paste everything below the line into Claude Design. Attach screenshots of the current screens listed in "Current screens" (one per route). Plan and audit: `docs/UX-PLAN.md`, `docs/UX-AUDIT.md`.

---

Design ROUGH, LOW-FIDELITY FLOW SKETCHES for "Foundry ERP", a web ERP for small and mid-size iron foundries (grey and ductile) in Pakistan. These are wireframes to test flows, not a polished UI. Greyscale, simple boxes, real labels, no illustrations. Font: Instrument Sans.

This is a RESHAPE of screens that already exist. The attached screenshots are the current screens. Keep what works, change the flow, do not invent new modules.

FIXED DECISIONS (do not change)
- Pakistan first: PKR, FBR / SRB / PRA sales tax, withholding.
- Record codes are short: a prefix and a running number (SO-1042, PO-88, HT-312, LOT-5531). No dates, long IDs or random parts.
- Roles stay as they are. The person who plans is the Production manager. The owner logs in as Administrator. What each person sees comes from their role's permissions, so the same screen shows less for a role with fewer permissions.
- "Simple mode" lives in Settings only. No mode toggle on working screens.
- Process depth: each optional step is a rule. The Basic rule set hides it, the Full set shows it.

PRINCIPLES
- Few screens. Basic order to dispatch is 7 screens; the real floor day is 14.
- Each person opens on ONE "due now" home: the work waiting for them, most urgent first. Today there are two (a home of count tiles, and a Today board for the Basic flow). Merge them into one, filtered by role permissions.
- Office: desktop. Floor: tablet, large tap targets, numeric keypads, one task per screen, bottom gap of at least 28px so Android buttons never cover the tab bar or sticky buttons.
- Every number or warning links to the screen that fixes it.
- Mistakes are fixed in place with a reason, never a dead end.

PERSONAS, in design priority order
1. PRODUCTION MANAGER as planner (desktop). Turns open orders into tomorrow's moulding list and the melting plan. Needs: orders by due date, moulds already made, shortages (flasks, metal), work spread over moulding stations, edit rows, issue the list.
2. MOULDING SUPERVISOR (tablet at the station). Needs: today's list, enter moulds made per pattern, count moulds before pouring (a shortfall needs a reason and is written off, and those orders need the moulds again), flask shortages.
3. OWNER (Administrator; phone and laptop). One glance: will today's orders ship, what is stuck and why, what is overdue, money in and out (PKR).
4. FURNACE OPERATOR (Melt shop supervisor; tablet near the furnace, gloves). Needs: next heat, start heat with the furnace's default charge recipe, charges due at set times, tap, pour gate, pour, close heat (furnace off time, metal left and where it went, unaccounted metal shown).
Already designed, do not redo: the quality technician phone screens.

DELIVER, as separate frames with arrows between them
A. The merged "due now" home for each of the 4 personas (4 frames).
B. FLOW 1, Order to dispatch, Basic: Quick order, home, Moulds made, Next heat and Start heat, Moulds poured, Quick QC, Pack and Dispatch.
C. FLOW 2, The planner's evening: open orders, prepare moulding list, adjust rows and stations, resolve a shortage, issue the list, the list shows on the moulding supervisor's tablet.
D. FLOW 3, Moulding station: list, enter moulds made, count moulds, shortfall with reason, ready to pour.
E. FLOW 4, Furnace: next heat, start with recipe, charge reminders (one overdue), pour gate, close heat with unaccounted metal.
F. FLOW 5, Owner check-in: home, open one stuck order, find the cause, pass it to the production manager or supervisor.
G. For each flow: one empty-state frame and one warning frame (flask shortage, overdue charge, shortfall).

FOR EVERY SCREEN
- Show the Basic and Full versions side by side when they differ, and say what Full adds.
- Title each frame with persona, device and step number.
- Label the primary action, secondary actions, and where each leads.
- Mark data entered once and reused later (heat, cavity mark, lot).
- For every hand-off to another person, add a note: how do they find out (list appears, alert, status change)? Flag it "NO TRIGGER" if there is none.

OUTPUT
- One page per flow, frames left to right, arrows for navigation.
- A final page: every screen with persona, device and flow, every "NO TRIGGER" hand-off, and up to 5 open questions for me.
- Do not add features I did not describe. Ask before inventing screens.

---

## Current screens to screenshot and attach

| Persona | Routes |
|---|---|
| Production manager (planner) | `/home`, `/quick/today`, `/planner`, `/work-lists`, one `/work-lists/{id}`, `/heat-plan`, `/production-orders` |
| Moulding supervisor | `/quick/floor`, `/moulding-runs`, one `/moulding-runs/{id}`, `/mould-count`, `/mould-readiness` |
| Owner | `/home` as Administrator, `/dashboard`, `/reports/yield`, `/approvals` |
| Furnace operator | `/quick/next-heat`, `/heats`, one running `/heats/{id}` (Charge tab), `/heats/create`, `/pours/create`, close heat on a heat page |
| Basic flow | `/quick/orders/new`, `/quick-qc`, `/packing`, `/dispatches` |
