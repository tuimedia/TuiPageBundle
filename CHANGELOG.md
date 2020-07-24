# Changes

## 0.8.5

### FIXED

* Exceptions thrown within the SearchSubscriber are no longer fatal. Instead they're logged as errors and execution continues.

## 0.8.4

### FIXED

* Implement missing `search` permission check

## 0.8.3

### NEW

* A new `access_control` configuration array contains roles to check before performing each of the write actions on the API. This works in addition to the existing advice to set up an `access_control` rule on the security component. See the README for details on how to configure this.


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


