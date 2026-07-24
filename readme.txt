=== Nizamiye ===
Contributors: ahmethantalha
Tags: education, attendance, gradebook, student management, reports
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Term-based student tracking for schools and boarding schools: attendance, grades, habit tracking and PDF report cards.

== Description ==

**Nizamiye** is a comprehensive, term-based student tracking plugin built for boarding schools,
schools and educational institutions. From student, teacher and parent management to attendance,
grade entry, habit tracking and visual reports, it brings together the essential tracking tools
an institution needs in a single panel.

= Key Features =

* **Student / Teacher / Parent Management** — a dedicated WordPress user role for each role, with direct redirection to the panel after login.
* **Classroom management** — quick student assignment via a class filter and bulk-selection roster screen.
* **Category and session based attendance** — in addition to regular class attendance, general categories such as prayer times, cleaning duty and phone checks; the administrator can define new categories/sessions and choose which grade levels each category applies to.
* **Habit tracking** — Done/Not done, graded (1 to N), or book/page tracking (daily book title + pages read) methods.
* **Grade entry and bulk upload** — classroom-based exam grades; CSV template download and bulk upload support.
* **Reports** — analysis tabs for attendance, habits, grades and overall performance, grouped by student or class; date-range or month/year filtering.
* **PDF Report Cards** — generates a real, downloadable `.pdf` report card for every student; from the Report Cards page you can select multiple students and download their report cards in bulk as a **single ZIP file** (one PDF per student).
* **Bulk import** — import student/teacher/parent lists via Excel (.xlsx) or CSV, with sample templates.
* **Term transition** — when a new term opens, all students are automatically promoted to the next grade; those at the graduation level are marked as graduated and archived.
* **Record-level access control** — teachers only see their own classrooms/students, parents only see their own children.

= Roles =

* **Administrator** — full access.
* **Teacher** — attendance/grade/habit entry for their own classrooms, viewing their own students.
* **Parent** — viewing only their own children's report cards.
* **Student** — viewing only their own report card.

= Third-Party Library =

PDF report card generation is done server-side using the [Dompdf](https://github.com/dompdf/dompdf)
library (LGPL licensed, GPL compatible), which is bundled with the plugin. Dompdf is configured
so it never makes requests to remote servers (`isRemoteEnabled` is disabled); the plugin does not
send any data to external services.

== Installation ==

1. Upload the plugin files to the `wp-content/plugins/nizamiye` folder (or upload the
   zip file via **Plugins → Add New → Upload Plugin**).
2. Activate *Nizamiye* from the **Plugins** page.
3. Open the **Okul Yönetimi** menu that appears in the left sidebar, go to **Settings**, and set
   your institution's name and the graduation grade level.
4. Create your first term from the **Terms** page, then add or bulk-import your teacher/parent/
   student records.

== Frequently Asked Questions ==

= Is student data deleted when the plugin is uninstalled? =

No, data is preserved by default. If you also want the data to be removed, add the line
`define( 'NIZAMIYE_REMOVE_DATA_ON_UNINSTALL', true );` to your `wp-config.php` file.

= Does the plugin send data to any external service? =

No. All data is stored in custom tables prefixed `wp_nizamiye_*` in your own database; no requests
are sent to any remote server. PDF generation is also done entirely server-side with the bundled
Dompdf library.

= I need multiple classrooms/sections, is that supported? =

Yes. Classrooms are defined as independent sections (e.g. "Turkish 6-A"), and each classroom can
have a teacher and students assigned to it.

= Can I define custom attendance categories, like prayer times? =

Yes. From the **Attendance Types** page you can define new categories and sessions (times/slots),
and choose which grade levels each category appears in.

= Can I download report cards in bulk? =

Yes. On the **Report Cards** page you can select students with checkboxes (including "select all")
and download a single ZIP file containing a separate PDF for each student.

== Screenshots ==

1. Dashboard — class-based summary and attendance participation tables.
2. Attendance entry screen — category/session cards.
3. Reports — attendance/habit/grade analysis tabs.
4. Student report card — attendance summary, habits and grade averages.

== Changelog ==

= 1.3.1 =
* The report card's "Attendance Summary" now shows a separate percentage for each attendance
  category (previously all categories were shown mixed into one total); multi-session categories
  (such as prayer times) are listed with their sub-breakdowns.
* Added bulk student selection to the Report Cards page and bulk ZIP download of the selected
  students' report cards (one PDF per student).
* PDF report card download now relies on a real `.pdf` file generated server-side with the
  bundled Dompdf library, instead of the browser's print function.
* When adding a reading habit, books the student has previously read are now suggested
  automatically.

= 1.3.0 =
* Added grade-level restriction for general attendance categories (e.g. prayer times).
* Added book/page tracking habit type.
* Added month/year based date filter to reports.
* Limited the "Recent Attendance" list on the report card to 3 records.

= 1.2.2 - 1.2.4 =
* Improved mobile sidebar and attendance screen layout.
* Post-login performance: repeated queries are now cached, and the database version check now
  only runs in the admin panel.
* Added session (time slot) based breakdown to attendance reports.
* Fixed an issue where logging out from the profile menu did not work on mobile.

= 1.2.0 =
* Added analysis report tabs, a safer bulk grade upload flow, and interface simplification.
* Added a CSV export button to analysis tables.

= 1.1.0 =
* Added bulk import (Excel/CSV) and category/session based attendance system.

= 1.0.0 =
* Initial release: term-based student tracking infrastructure, student/teacher/parent
  management, basic attendance, grade and habit tracking.

== Upgrade Notice ==

= 1.3.1 =
Report card PDF generation now happens server-side and bulk ZIP download is supported; no extra
steps are required after updating.
