Object connections with Midgard MVC
===================================

When you want to provide a list of content objects with your application, it is a good idea to do it with a Collection. Collection is basically just an iterable class that knows where the objects came from, and is able to handle creating and deleting objects.

The collections API is still up for discussion. Here are the two options of how to handle deletes and creations within the collection:

* Callbacks: when populating a Collection, the controller will also register creation and deletion callbacks that external applications can use
* Empty object template and standard methods for content objects: when populating the Collection, the controller also provides a template of an empty objects (with attributes required for the collection populated with appropriate values). The objects themselves provide `create()` and `delete()` methods
