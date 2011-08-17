skValidatorHTMLPlugin
=====================

This symfony plugin contains a validator for HTML content. It strips out any unwanted tags, attributes and entities, and ensures the markup is well-formed.



Usage
-----

To see the options available, check the code in `skValidatorHTML::configure()`.



License and Attribution
-----------------------

skHTMLValidatorPlugin by [Studio Skylab](http://www.studioskylab.com)
This code is released under the [Creative Commons Attribution-ShareAlike 3.0 License](http://creativecommons.org/licenses/by-sa/3.0/).

All the hard work was done by the following:

* Cal Henderson — The [lib_filter](https://github.com/iamcal/lib_filter) class upon which this plugin is based
* Jang Kim — Support for single quoted attributes
* Dan Bogan — Entity decoding outside attributes