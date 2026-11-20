# PRISM
**Performance Ranking and Integrated Scoring Management** · Tabulation Management System
*Accuracy. Transparency. Excellence.*

© Team Tabulators of STI Alaminos. All rights reserved.

Laravel 12 + Inspinia (Bootstrap 4) system for tabulating pageants and other judged competitions. Several events can run at the same time.

## Features
- **Events**: pageant, singing, dance, talent, debate or other. Add them from *Events → Add Event*.
- **Rounds → Categories → Criteria** with weights (e.g. Preliminary → Top 5 → Finals).
- **Divisions** (e.g. Mr / Ms): rankings are computed separately for each division.
- **Two scoring methods**
  - *Weighted average*: the highest average across judges wins.
  - *Rank sum*: each judge's scores are converted to ranks, and the lowest total rank wins.
- **Event-day workflow (admin)**
  1. Create the event and its rounds, categories, criteria and contestants.
  2. **Judges & Tabulator** tab: type the judges' names, and the system generates a username and password for each (`ev<event>-judge1`, `ev<event>-judge2`, ...). Click *Generate tabulator account* for the person running the event. Print the login slips right away, because passwords are shown only once. *New password* makes a new one if a slip is lost.
  3. Click **Set Active** to start the tabulation and **Set Inactive** to pause it. While inactive, judges can't score and don't see the event. Several events can be active at the same time. **Mark Completed** when finished.
- **Tabulation History** (admin): past events with final results and re-printable sheets, a **score change log** of every score a judge entered or changed (old → new, time, PC address; fast typing on one cell is merged into one entry), and an **activity log** of logins, failed logins, Active/Inactive switches, categories opened or locked, sheets submitted or unlocked, and accounts created or reset.
- **Dark mode**: the *Theme* button in the top bar or on the login page. It follows the computer's own setting until someone picks one, and is remembered per PC.
- **Three roles**
  - *Administrator*: sets up events, rounds, criteria, contestants, judges and user accounts.
  - *Tabulator*: on event day, watches the scoring monitor and live results, opens and locks categories, unlocks a judge's sheet, and prints results. A tabulator generated for an event sees only that event; one created under *Users* with "All events" sees every active event. A tabulator cannot change the event setup.
  - *Judge*: scores the events they are assigned to.
- **Saves as the judge types**: every keystroke in a score cell is saved to the server within about 0.3 s, and right away when the judge leaves the cell. Unsaved scores are also kept in the judge's browser. If the network drops or the page is reloaded, they are sent automatically once the connection is back. Submitting a sheet locks it.
- **Live scoring monitor** shows each judge's progress per category, updated about 1.5 s after a judge types.
- **Live results**: per-category and overall rankings that update as scores come in, plus printable summary and detailed result sheets with signature lines.
- **Advance top N** finalists from one round into the next (e.g. Top 5), per division, with ties at the cut-off included.
- **Previous-round bearing per round** (*Rounds & Criteria → edit round*): `0%` means scores go back to zero and earlier rounds have no bearing (the default). For example, `30%` makes the final score 70% of this round plus 30% of the previous round's score. It works with both scoring methods and is shown on the results and printed sheets.
- **Live results as charts**: on the Results page, switch between *Tables*, *Standings* (ranked bars), *Breakdown* (points per category, stacked), *Judges* (heatmap of each judge's scores, for spotting disagreement), *By Category* (leaders in each category) and *Top 3* (podium, for a projector). All views update live, have hover details, and work offline. Each PC remembers its last view.
- **Logos** (admin → *Logos*): upload as many logos as needed (school, LGU, sponsors). Mark a logo **Show on every event**, or pick which events show it (in *Logos* or *Event → Edit*). They appear as a band at the top of the judges' screens, and optionally on printed results and the login page. Size and these options are in *Settings*.
- **Judges' screens update by themselves**: any change to Settings, Logos or an event's layout or logos reaches every judge screen within about 3 seconds, with no refresh needed. **Refresh judges' screens** (on *Settings* for all events, or on an event's *Scoring Monitor* and *Judges & Tabulator* pages for that event) reloads them on demand. Unsaved scores are always saved first. The monitor also shows which judges are **Online**.
- **Export results**: on the Results page, *Excel* downloads an `.xlsx` workbook with an overall sheet per division, a sheet per category showing every judge's score, and a raw criterion-score sheet. *PDF* downloads a summary or detailed (every judge, every category) result sheet with logos, signature lines and page numbers. *Print* is still available. The same downloads are on *Tabulation History*.
- **Highlight colour** (*Settings → Appearance*): choose a preset or any colour. The whole system and the judges' screens follow it, with text colours picked automatically to stay readable in light and dark mode.
- **Unlocking** a judge's submitted sheet reloads that judge's screen right away. Opening or locking a category reloads that event's judges.
- **Logos** uploaded by the admin appear on the right of every page heading: the event's own logos on event pages (including the judges' screens), and the "every event" logos elsewhere.
- **Settings** (admin): system name and organization (shown on the login page and printed results), and the **judge score-sheet layout**: *Score Table*, *Contestant Cards*, *One at a Time*, *By Criterion* or *Compact List*. Display options cover photos and photo size, names (turn off for blind judging), details, running totals, the judge's own ranking, contestant order, large touch-friendly score boxes, the criteria guide, and whether judges may switch layouts themselves. Any event can override the layout (*Event → Edit*), and *Preview judge view* shows exactly what judges will see.

## Running locally (XAMPP)
```
C:\xampp\php\php.exe artisan migrate:fresh --seed
C:\xampp\php\php.exe artisan serve
```
Open http://127.0.0.1:8000, or via Apache at http://localhost/tabulatesys/public.

Demo accounts (password `password`): `admin`, `tabulator`, `judge1` – `judge4`.

## Running on the event network (judge PCs + tabulator PC)
One computer is the **server**. Every judge PC and the tabulator PC connect to it over the same Wi-Fi or LAN using a web browser. Nothing needs to be installed on the judge PCs.

**Recommended: XAMPP Apache.** It handles many judges saving at the same time.
1. On the server PC, open the XAMPP Control Panel and start **Apache**. You do not need MySQL.
2. Find the server's IP address: run `ipconfig` and look for "IPv4 Address", e.g. `192.168.1.8`.
3. On every judge and tabulator PC, open `http://192.168.1.8/tabulatesys` (use your server's IP).
4. If other PCs can't connect, allow Apache through Windows Firewall. In an **administrator** PowerShell on the server, run:
   `netsh advfirewall firewall add rule name="Tabulation System" dir=in action=allow protocol=TCP localport=80,8000 profile=private,domain`
   Also make sure the Wi-Fi/LAN network is set to **Private** in Windows settings.

**Quick alternative:** double-click `start-server.bat`. It runs `php artisan serve --host=0.0.0.0 --port=8000` and prints the address to open on the other PCs (`http://<ip>:8000`). On Windows this server handles only one request at a time, so prefer Apache when more than 2–3 judges are scoring.

**Event-day checklist**
- Admin: open the event's **Judges & Tabulator** tab, generate the judge and tabulator accounts, print the login slips, then click **Set Active**.
- Tabulator: log in and open the event's *Scoring Monitor*. Open a category so judges can score it, watch the progress and results update live, then lock the category when everyone has submitted.
- Judges: log in on their own PC, open the category and type scores. Each one is saved automatically, and the header shows "All scores saved". Click *Submit Final Scores* when done.
- Give the server PC a fixed IP, or reserve it in the router, so its address doesn't change during the event.

The root `.htaccess` lets Apache serve only the `public/` folder, so `.env` and `database/database.sqlite` cannot be downloaded from other PCs.

### Database
By default the app uses SQLite (`database/database.sqlite`) in WAL mode with a 10 s busy timeout, so several judges can save at the same moment. To use MySQL, edit `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tabulatesys
DB_USERNAME=root
DB_PASSWORD=
```
Then run `php artisan migrate:fresh --seed`.

## Tests
```
C:\xampp\php\php.exe artisan test
```
