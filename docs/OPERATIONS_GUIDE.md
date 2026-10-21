# Operations Guide for Super Admin (Non-Technical User Manual)

**Ultimate Security Services (USS), Dubai, UAE**  
Welcome to the USS Online Incident Reporting System. This guide explains how to perform daily operations without technical knowledge.

---

## 1. Logging In as Super Admin

1. Open your web browser (Chrome, Edge, or Safari) and go to:
   **`https://incidents.ultimatesecurity.ae/admin/login`**
2. Enter your **Super Admin Username** and **Password**.
3. Click **Sign In**.
4. You will see the **Live Dashboard** with key performance metrics (Today's Incidents, Urgent Alerts, Open Follow-ups, and Active Sites).

---

## 2. Managing Sites & Generating QR Codes

Each building or client facility has its own unique QR code poster.

### Adding a New Site:

1. In the sidebar, click **Sites**.
2. Click **+ Add New Site**.
3. Fill in:
   - **Site Code**: e.g., `BUR01`
   - **Site Name**: e.g., `Burj Crown Tower`
   - **Site Type**: Choose `Commercial`, `Residential`, or `Hospital`.
   - **Address**: Building address or area in Dubai.
   - **Client Manager Contact**: Name and email.
4. Click **Create Site**.

### Downloading the Printable QR Code Poster:

1. In the Sites table, click **QR Poster** next to the site.
2. A printable A4 poster will open showing:
   - The USS company logo
   - The building name and site code
   - The high-resolution QR code
   - The human-readable short link for guards
3. Click **Print Poster** or **Download PNG** to print and mount at the security desk.

---

## 3. Creating Guard Accounts (Security Staff)

Guards use their accounts only to submit incident reports. They cannot see or modify admin settings or other reports.

### Adding a Guard:

1. In the sidebar, click **Security Staff**.
2. Click **+ Add Guard Account**.
3. Enter:
   - **Staff ID**: e.g., `USS-1042`
   - **Full Name**: e.g., `Muhammad Tariq`
   - **Designation**: e.g., `Security Guard` or `Patrol Officer`
   - **Assigned Sites**: Select which site(s) this guard is authorized to patrol.
   - **Username**: e.g., `tariq.guard`
   - **Password**: Type a password or click **Generate Strong Password**.
4. Click **Save Guard**.
5. **Print Credentials**: Click **Print Credentials Slip** to give the guard a physical slip showing their Staff ID, Username, and Password.

---

## 4. How Guards Submit an Incident (Guard Patrol Flow)

1. The guard arrives at the incident location.
2. The guard scans the site's QR poster with their **regular phone camera**.
3. The guard login page opens automatically on their mobile phone with the site preselected.
4. The guard types their username and password.
5. The guard fills the report:
   - **Header fields**: Date, Time, Shift (Site Name, Person Reporting, and Designation are pre-populated).
   - **Severity** (single choice): Critical (Urgent trigger), High, Medium, Low.
   - **Disciplinary** (multiple choice): Sleeping, Poor Grooming, Argument, Missing, Misbehavior, Uniform, Late Reporting, Mobile, Others.
   - **Incident Categories** (multiple choice): Move In & Out, Fire & Safety (Urgent), Intrusion & Access (Urgent), Theft and loss (Urgent), CCTV & Systems, Utilities & Building, Accident & Medical (Urgent), Disturbance, Property Damage, Parking & Vehicles, Visitors & Contractors, Others.
   - **Who was notified?** (multiple choice): Police, Ambulance, Civil Defense, Client Management, USS Management, Others.
   - **Custom Fields**: Any dynamic custom fields added by the Super Admin.
   - **Description of Incident**: Formatted rich-text narrative following the narrative bar prompt: _(Explain What, Where, When, Who, Why and How action taken)_. Visible minimum 15 lines.
   - **Photos**: Attaches up to **10 photos** taken directly with the phone camera.
6. The guard clicks **Submit Report**.
7. Once the server confirms receipt, a confirmation screen appears with the reference number (e.g., `INC-BUR01-20261016-0001`), and the guard is **automatically logged out**.

---

## 5. Reviewing & Editing Incidents in the Dashboard

1. Click **Incident Reports** in the sidebar.
2. Use the filters to find incidents by **Site**, **Date Range**, **Incident Type**, or **Status**.
3. Click on any report to open the **Full Incident Detail**:
   - Inspect the formatted rich-text narrative.
   - Click photo thumbnails to view them in the high-resolution lightbox.
   - In the **Sign-Off & Follow-Up** tab, add your **Recommendation / Action Taken** and supervisor notes.
   - Update the status to `Under Review`, `Follow-up Required`, or `Resolved`.
4. Click **Save Changes**.

---

## 6. Exporting Reports (PDF & Excel)

Only two export formats are provided, strictly matching USS company standards:

### Single Incident PDF:

- On any incident row, click the **PDF** button.
- The official USS Incident Report PDF generates instantly matching the **01-04-2023 Official Form layout**:
  - Company block top-right (smaller company name, P. O. Box No: 61591, Al Maktoum Street, DUBAI | U.A.E, Email: ops@ultimatesecurity.ae, zero fax).
  - Single collapsed border lines (0.75pt, no double lines).
  - Auto-reflowing grid for options without overlapping text.
  - Exactly 15 full lines for the Incident Description on the primary A4 page.
  - Continuation pages cleanly flow overflow text and photos on separate pages.
  - Sign-off blocks remain grouped together at the bottom.

### Daily Consolidated Client Report:

- In the sidebar, click **Daily Reports**.
- Select the **Site** and **Date**.
- Click **Export Daily PDF** or **Export Daily Excel**.
- The PDF contains a consolidated summary cover page followed by every incident of that day.
- The Excel file contains formatted tabs with frozen header panes, auto-fit columns, filterable registers, and dynamic column headers for custom fields.

---

## 7. Managing Form Fields (Incident Form Fields Builder)

The Super Admin can modify the incident report form fields at any time from **Settings > Incident Form Fields**:

1. **Rename Labels**: Rename any field label, left-cell label (e.g., Severity, Disciplinary, Incident), narrative bar text, "Who was notified?", or sign-off headings.
2. **Add / Reorder Options**: Add new options to any group (e.g., "Gas Leak" to Incident Categories) or reorder with drag-and-drop or touch controls.
3. **Add Custom Groups & Fields**: Create new checkbox/radio rows (e.g., "Weather Condition") and custom data fields (text, number, dropdown, yes/no).
4. **Draft & Live Preview**: Edits are saved as a working draft. The live dual-panel preview displays both the **Mobile Form** and **PDF page** in real time.
5. **Preview PDF**: Generates a test PDF with draft labels and sample data to verify page fit before publishing.
6. **Publish Immediately**: Changes propagate instantly to open guard forms, admin screens, PDFs, Excel exports, and alerts via SignalR without code redeployment.
7. **Version History & Rollback**: One-click rollback to any historical version with full audit trail logging.
8. **Reset to Factory Defaults**: Restores the original 01-04-2023 PDF default labels and groups at any time.

---

## 8. Updating Company Branding (Company Setup)

If company details or phone numbers change:

1. In the sidebar, click **Settings > Company Setup**.
2. Update **Company Name** (defaults to `ULTIMATE SECURITY SERVICES`, spelling editable), **Address Line 1** (`P. O .Box No: 61591`), **Address Line 2** (`Al Maktoum Street, DUBAI | U.A.E`), **Email** (`ops@ultimatesecurity.ae`), or optional **Office Phone**.
3. Upload a new company logo (e.g. Hexagon "S" logo); it updates immediately across all screens, PDFs, Excel sheets, and QR posters.
4. Set **Historical Report Display Mode**: choose between _Current Labels (default)_ or _Labels at time of submission_.
5. Click **Save Company Details**.
   _(Note: As per USS company policy, fax numbers are permanently discontinued and excluded)._
