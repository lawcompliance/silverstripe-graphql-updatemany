# GraphQL Update Many

## Introduction
Adds support for the updateMany operation. The input for each model is delegated to the corresponding update resolver.

## Requirements
* SilverStripe CMS 4.0

## Usage
Just like other operations, enable this operation on a model by doing something like the following

```
  Silverstripe\Security\Member:
    operations:
      update: true            #dont forget to allow update operations, as updateMany delegates to the update resolver
      updateMany: true
```
 
 
## Example mutations 
(in combination with the nested mutatations module)

```
mutation{
  updateMembers(input: [
    {
      id: 2
      firstname: "test1"
    	address:{
        id: 36
        suburb: "Valencia"
      }
    },{
      id: 3
      firstname: "test2"
      address:{
        suburb: "France"
      }
    }
  ]){
    id
    firstName
    address{
      id
      suburb
    }
  }
}
```
