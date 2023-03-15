# CHANGELOG

All notable changes to this project will be documented in this file.

## v1.8.0
* New EPFL Form Template
* Minors fixes

## v1.7.3
* Renew the EPFL Conference Form Template

## v1.7.2
* Hide all forms payment methods but EPFL Payonline

## v1.7.1
* Fix for Force WP_Filesystem autodetection (`FS_METHOD`)

## v1.7.0
* Force WP_Filesystem autodetection
* Set allowed redirect hosts
* Fix deprecated `wpforms_conditional_logic()->conditionals_block()`

## v1.6.0
* Adaptations for WPForms 1.7.0
* Hide some new marketing button / entries
* s/IDEV-FSD/ISAS-FSD
* https://github.com/epfl-idevelop → https://github.com/epfl-si
* A lot of lint fixes
* Chores

## v1.5.0
* Use form's admin email for notification instead of blog's admin email, when it is not redefined.
* Change author to IDEV-FSD

## v1.4.0
* Reply to email as defined in the settings

## v1.3.0
* Logging and debug logs refactored
* Makefile improvements
* Documentation started

## v1.2.0
* Developer mode removed

## v1.1.0
* Hide some add-ons
* Add composer.* to .gitignore
* Add WordPress Coding Standards to CONTRIBUTING.md

## v1.0.9
* FIX HTML char in sent emails
* Set the example form to large to improve readability

## v1.0.8
* FIX enforce default EPFL sender address

## v1.0.6
* Conference form template updated
* Better user's information
* FIX for https://developer.github.com/changes/2020-02-10-deprecating-auth-through-query-param

## v1.0.4
* Plugin zip contains a subdirectory with the same name, for wp-ops/Ansible

## v1.0.2
* Hide some payment addons

## v1.0.0
* README update

## v0.0.20
* Improved compatibility with WPForms version 1.5.8.2
* Code cleanup

## v0.0.19
* Compatibility with latest WPForms version 1.5.8.2
* Moar logs

## v0.0.18
* Addition debug logs

## v0.0.17
* Get rid of PHP 7 array '[]'

## v0.0.16
* Translation updated

## v0.0.15
* Functions `getFieldsFromType` and `getArraysFromType` to manage to get
  form fields data based on field type.
* Debbuging cleaned up
* CC payment confirmation to form manager
* Better address management

## v0.0.13
* Translations fix
* Link to Payonline paiement details added

## v0.0.12
* Quick fix
* Releasing: small improvements

## v0.0.11
* Payment details improvements
* Releasing: improved process that check CHANGELOG
* Code clean up

## v0.0.10
* Fix plugin "View details" link
* Releasing: do not commit unstaged change by default

## v0.0.9
* SemVer management and releasing scripts improved

## v0.0.8
* Use of http://parsedown.org to display plugin info based on README, INSALL and CHANGELOG

## v0.0.7
* Auto update thanks to https://rudrastyh.com/wordpress/self-hosted-plugin-update.html

## v0.0.6
* create-gh-release.sh improved
* Release asset keep the same name so that [the latest release download
link](https://github.com/epfl-si/wpforms-epfl-payonline/releases/latest/download/wpforms-epfl-payonline.zip)
is always available

## v0.0.5
* Auto create the plugin zip and release it on GitHub with `make all`
* French translation

## v0.0.4
* Makefile to automatize all the things (WIP)
* Translation files generated

## v0.0.3
* Payments status updated from Payonline
* Default form template for conference

## v0.0.2
* Payments sent to Payonline
* Plugin auto-activated
* Amazing logo created

## v0.0.1
* wpforms-paypal-standard plugin as template
