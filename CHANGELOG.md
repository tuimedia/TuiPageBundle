## 0.8.1

### FIXED

* Fixed an exception in PageSchema while trying to validate a block with no langData in the default language.

## 0.8

### BREAKING

* The data format has changed:
    * `PageData.content` has an additional integer property: `schemaVersion`. If not provided, the old format is assumed.
    * `PageData.content.layout` is now an array of block ids. Layout blocks were always another kind of block, so keeping them together makes sense and reduces the amount of code.

### NEW

* A `pages:upgrade` command to migrate content from the old format to the new. This command is repeatable without problems, so you can make it part of your deployments. BACK UP YOUR DATABASE BEFORE RUNNING THIS.


