# RepeaterMatrix Changelog

### Version 11, September 2023

- Maintenance release version

### Version 9, February 2023

- Maintenance release version

### Version 8, 2022

- Maintenance release version

### Version 7, October 2021

- Add support for Select, Images and Custom add-types.
- Add support for insert before/after.
- Add support for inline clone.
- Add support for label groups (used with Select add-type).
- Add support for custom icons per-type (defined in label).
- Add new supported properties to type labels.
- Various other code improvements and minor fixes.
- New API methods via new RepeaterMatrixField custom Field class.
- Moved several methods from FieldtypeRepeaterMatrix to RepeaterMatrixField,
  the moved methods are now deprecated in FieldtypeRepeaterMatrix class.

### Version 6, 2020-2021

- Added support for family-friendly depth and drag features.
- New API methods added to FieldtypeRepeaterMatrix class.
- Various minor fixes.

### Version 5, May 2019

- Added support for changing the matrix type of an item. To change the type,
  click the gear icon that appears in the header of each item. A box will open
  where you can select the new matrix type.

- Added support for importing types from other RepeaterMatrix fields. To use,
  click the "Import Matrix Type" button to the right of the "Add Matrix Type"
  button. It will give you a select box where you can choose which to import.

- Add support for field/template context enabling you to select which matrix types
  are shown for a Matrix field on a per-template basis.

- Added detection of when a Matrix type name can collide with a field name.

- Several new methods (on FieldtypeRepeaterMatrix instance) were added. Below is
  a list of these methods, but see the actual method phpdoc for more details:

    - getMatrixTypesInfo($field)
      Get all information available for each matrix type in an array of arrays
      indexed by type name.

    - getAllMatrixTypesInfo()
      Get verbose information array for all RepeaterMatrix fields in the system.

    - getMatrixFields()
      Get an array of all RepeaterMatrix fields.

    - getMatrixTypeByName($name, $field = null)
      Return matrix type (integer) for given name, or boolean false if not found.

    - getMatrixTypeLabel($type, Field $field = null, $language = null)
      Get the label for the given matrix type (name or number).