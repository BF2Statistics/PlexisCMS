# Database Compatibility in ASP

## Overview
The ASP framework offers flexibility in database connectivity, providing support for different relational SQL databases. 
This compatibility is achieved through the implementation of database-specific drivers. These drivers act as an interface 
between the framework and the database systems, allowing developers to seamlessly interact with the database of their choice.

## Key Features
1. **Driver-Based Architecture:**
   The ASP uses a driver-based architecture, enabling support for various relational databases such as:
    - MySQL
    - PostgreSQL
    - Microsoft SQL Server
    - SQLite
    - Oracle Database

2. **Plug-and-Play Configuration:**
   Developers can easily switch between supported databases by configuring the respective driver. No additional changes in the application logic are required.
3. **Standardized Communication:**
   Drivers translate standardized SQL queries into database-specific operations, ensuring consistent behavior across different databases.
4. **Support for Scaling:**
   By choosing appropriate database drivers, the ASP enables developers to scale applications based on their requirements.

## Setting Up a Driver
To use a specific relational SQL database with the ASP:
1. Ensure the required driver for the target database is installed. 
2. Configure the driver within the ASP config page or installer (e.g., specify driver name, database connection URL, credentials).
3. Test the connection to confirm successful integration

## Creating a new Driver
1. Each driver must have its own folder within the "system/sql" folder, and must be structured as so:
    - migrations (folder)
      - up (folder)
      - down (folder)
    - data.sql -> Contains the default data to be inserted into the database after the tables are created.
    - schema.sql -> Contains the schema and table creation SQL queries.
    - metadata.json -> Contains the full name, version requirements, and author metadata for the driver.
2. The ASP config and install pages will automatically update with the driver populated in its dropdown fields as long as the
    metadata.json file is formated correctly. Please see the included driver as an example.
3. If you encounter any issues with database compatibility, please create an issue on GitHub and I will take a look.
