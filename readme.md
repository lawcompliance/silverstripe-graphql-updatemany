# GraphQL Update Many

## Introduction
Adds support for an updateMany scaffolder

## Requirements
* SilverStripe CMS 4.0

## Usage
Just like other CRUD scaffolders, allow this scaffolder on a dataobject by doing soming like the following

```
SilverStripe\GraphQL\Controller:
  schema:
    scaffolding:
      types:
        Silverstripe\Security\Member:
          operations:
            update: true            #dont forget to allow update operations, as updateMany delegates to update
            updateMany: true
```
 