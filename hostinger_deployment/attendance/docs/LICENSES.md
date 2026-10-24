# Third-Party Libraries & Commercial Licensing Notice
**Module:** Attendance & Payroll Subsystem for Ultimate Security Services (USS)
**Date:** October 2026

The Attendance & Payroll module utilizes only strictly permissive, commercially compatible open-source libraries. None of these libraries are shared with or loaded by the core incident management application.

---

### 1. PhpSpreadsheet (Spreadsheet Engine)
- **Author:** PHPOffice Team
- **Website:** https://github.com/PHPOffice/PhpSpreadsheet
- **License:** MIT License
- **Permitted Use:** Commercial, private, distribution, and modification allowed without royalties.
- **Purpose in Module:** High-precision Microsoft Excel (`.xlsx`) generation for Attendance Sheets and Consolidated Payroll with native formulas (`COUNTIF`, `SUM`), cell formatting, frozen panes, and print scaling.

---

### 2. Dompdf (HTML to PDF Converter)
- **Author:** Dompdf Team / Fabien Ménager
- **Website:** https://github.com/dompdf/dompdf
- **License:** GNU Lesser General Public License (LGPL v2.1)
- **Permitted Use:** Commercial use permitted as a dynamic/unmodified library.
- **Purpose in Module:** Rendering strict single-page A4 Landscape Attendance Sheets with 20px row heights and border alignment.

---

### 3. Typography & Fonts
- **Primary:** Franklin Gothic Demi Cond / Arial Narrow / Liberation Sans Narrow
- **Status:** Uses system-rendered fonts or permissive OFL / Liberation fonts for PDF rasterization.
