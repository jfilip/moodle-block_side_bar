
Side Bar Block
==============

The modification contained herein was originally provided by [Remote-Learner Canada (formerly Open Knowledge Technologies)](http://www.remote-learner.net) and Fernando Oliveira of MoodleFN and First Nations Schools.

Contributors:

*	Justin Filip (jfilip@remote-learner.net)
*	Mike Churchward (mike@remote-learner.net)
*	Fernando Oliveira (fernandooliveira@knet.ca)

Installation
=============

To install, either unpack the downloaded zip file into the Moodle ``/blocks/`` directory or, using Git, clone the source code into the blocks directory like so:

    git clone -b MOODLE_26_STABLE https://github.com/jfilip/moodle-block_side_bar.git side_bar

This will create the following files:

    /blocks/side_bar/block_side_bar.php
    /blocks/side_bar/CHANGELOG.md
    /blocks/side_bar/config_instance.html
    /blocks/side_bar/db/access.php
    /blocks/side_bar/db/upgrade.php
    /blocks/side_bar/edit_form.php
    /blocks/side_bar/lang/en/block_side_bar.php
    /blocks/side_bar/lang/fr/block_side_bar.php
    /blocks/side_bar/locallib.php
    /blocks/side_bar/README.md
    /blocks/side_bar/reset.php
    /blocks/side_bar/tests/side_bar_test.php
    /blocks/side_bar/version.php

Visit the Administration Notifications page or use the CLI upgrade script to complete installation.

Function
========

This block allows you to create separate activities and resources in a course that do not have to appear in course sections. The block can have multiple instances of itself within a course with each instance having its own unique group of activities and resources. Each instance can also have its own configured title.

It functions by creating course sections for each block instance, starting at a number beyond what is curently visible within the course. This prevents the activities from appearing to users who do not have permission to manage content within the course.

If you add extra section to a couse using this block, a link will appear within the now-visible Side Bar block section that will execute a script to *move* the affected section (or sections when using multiple block instances) to be found after the last visible course section.

All resources and activities within a block can be edited and moved around just like normal activities when editing is turned on. Adding label resources allows you to add text to the blocks as well.

In a sense, this block combined the main menu block functions and HTML block functions into one block that can be used in a course.

Reporting bugs and suggesting improvements
==========================================

Please create new issues in the [official Moodle tracker Contrib project](https://tracker.moodle.org/browse/CONTRIB). Be sure to choose the *Block: Side Bar* component when creating the issue as it will be automatically assigned to the plugin maintainer by default.
