These files are only provided as-in, and are not part of ZfcRbac. They provide you some basic Doctrine ORM
entities that you can use as a starting point.

## Flat role or hierarchical role?

As you can see, there are a FlatRole and HierarchicalRole entity classes. You must only use one of them, not both.

The flat role is easier to follow, because each role contains all its permissions, and the database schema is easier
as you do not need a join table for the role hierarchy.

On the other hand, the hierarchical role is much more flexible, and prevent you from duplicating the same permissions
into all roles.

It really depends on your application.
