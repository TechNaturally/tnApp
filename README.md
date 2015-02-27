# tnApp
TechNaturally app
Aims to provide a modular architecture for building cloud apps.

## Components
The main components of the tnApp are:
* **Server providing a REST API** *(currently implemented in PHP)*
* **Client providing a user experience** *(currently implemented in angularjs)*

Modules may be added on both the server and client.

## Installation
Run:
```
cd app && composer install && cd ../www && bower install && cd ..
```

Be sure to edit `app/config.json` with your database config.

## Modules
### Server Modules
Server modules are comprised of two main pieces:

1. a descriptive **&lt;module&gt;.json** file
  * lists which fields (from the schema) to include in the database table
  * describes the data schema as a json-schema
  * describes API endpoints (aka "routes")
    * links to HTTP-method-specific logic implementation callbacks *(ex. myModule_myRoute_get)*
    * defines access permissions on a per-route/per-method basis
    * defines forms associated with a route/method callback *(based on angular-schema-form definitions)*
2. a logic **(ex. &lt;module&gt;.php)** implementation file
  * implements all route/method callback functions
  * implements any support functions needed by the module (ie. functions not-exposed as API routes)

### Client Modules
Client modules provide the user interface to the REST API.

## Technologies
#### Current
* **Server:** PHP
* **Datastore:** MySQL
* **Client:** angularjs

#### Planned
* **Server:** express.js
* **Datastore:** SQLite
* **Datastore:** Firebase
* **Client:** openFrameworks C++
