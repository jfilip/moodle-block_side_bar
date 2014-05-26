## 2.7.1 (unreleased)

IMPROVEMENTS:

* removed deprecated calls to cm_info::get_url()
* changed to remove title case from the block name, per Moodle convention
* added missing 'addinstance' string

## 2.7.0 (May 14, 2014)

NEW FEATURES:

* support for Moodle 2.7 [[CONTRIB-5019](https://tracker.moodle.org/browse/CONTRIB-5019)]

IMPROVEMENTS:

* updated README.md file to contain current information [[CONTRIB-5014](https://tracker.moodle.org/browse/CONTRIB-5014)]
* added CHANGELOG.md file to document changes for each release [[CONTRIB-5014](https://tracker.moodle.org/browse/CONTRIB-5014)]

## 2.6.0 (April 16, 2014)

NEW FEATURES:

* support for Moodle 2.6 [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]
* added unit tests for core functionality of the block [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]

IMPROVEMENTS:

* completely rewritten handling of the course section(s) that block activities are associated with [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]
* added an upgrade step to migrate legacy activity code to the new format [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]

BUG FIXES:

* replaced the non-working new style (Moodle 2.3+) activity chooser to use the old SELECT-based Resource and Activity chooser interface [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]
    * thanks to Kirill Astashov for the bug report and solution
