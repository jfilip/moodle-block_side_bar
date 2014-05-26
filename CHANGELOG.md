## 2.5.3 (May 26, 2014)

IMPROVEMENTS:

* changed to remove title case from the block name, per Moodle convention
* added missing 'addinstance' string

## 2.5.2 (April 30, 2014)

IMPROVEMENTS:

* added correct release version property in version.php

## 2.5.1 (April 28, 2014)

IMPROVEMENTS:

* updated README.md file to contain current information [[CONTRIB-5014](https://tracker.moodle.org/browse/CONTRIB-5014)]
* added CHANGELOG.md file to document changes for each release [[CONTRIB-5014](https://tracker.moodle.org/browse/CONTRIB-5014)]

DEVELOPER:

* cleaned up coding style complaints foundby running the [Moodle Code-checker plugin](https://moodle.org/plugins/view.php?plugin=local_codechecker) [[CONTRIB-5003](https://tracker.moodle.org/browse/CONTRIB-5003)]

## 2.5.0 (April 16, 2014)

NEW FEATURES:

* support for Moodle 2.5 [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]
* added unit tests for core functionality of the block [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]

IMPROVEMENTS:

* completely rewritten handling of the course section(s) that block activities are associated with [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]
* added an upgrade step to migrate legacy activity code to the new format [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]

BUG FIXES:

* replaced the non-working new style (Moodle 2.3+) activity chooser to use the old SELECT-based Resource and Activity chooser interface [[CONTRIB-3782](https://tracker.moodle.org/browse/CONTRIB-3782)]
    * thanks to Kirill Astashov for the bug report and solution
