## Multiselect Handling by Leuchtfeuer - Mautic 5 Version
Enhanced Mautics ability to handle the fieldtypes “Select” & “Multiselect”. Handling of fieldtype "Select" was introduced in later version, thus the naming "multiselect".


## Installation / Integration
* Folder name for this plugin has to be LeuchtfeuerMultiselectHandlingBundle.
* Clear the cache.
* In Mautic UI "Plugins" click "Install/Upgrade Plugins".
* Click on "Leuchtfeuer Multiselect Handling" and activate it with "Yes".
* In some cases you'll need to execute a console command `./bin/console mautic:assets:generate`.

## Features

### Form Action "Modify Segment Membership based on Multiselect"
1. Set up custom field(s) with multiselect or select type, which will contain a list of Segments for synchronization. Needs to have the same alias.
2. Map checkbox group or select field in the form to the custom field from 1
3. Add form action "Modify Segment Membership based on Multiselect"

After form is submitted the Contact will be added/removed to/from segments according to the selection made in the form fields.

### Form Action "Change contact's multiselect field"
1. Select managed multiselect field.
2. Select values to add to multiselect field. In case this value is empty no values will be added.
3. Select values to remove from multiselect field. In case this value is empty no values will be removed.

### Campaign action "Change contact's multiselect field"
1. Select managed multiselect field.
2. Select values to add to multiselect field. In case this value is empty no values will be added.
3. Select values to remove from multiselect field. In case this value is empty no values will be removed.

### Campaign action "Change contact's select field"
1. Select managed select field.
2. Select value to set as the value of a select field. In case this value is empty the value will be not set by this action.
3. Select values to remove from a select field. In case this value is empty no values will be removed.

### Campaign action: “Change Segment membership based contact field”
1. Select managed select or multiselect field
2. Contacts will be added/removed to/from segments corresponding to the chosen select / multiselect field values


Add or remove segments based on this contacts “select” or “multiselect” field values. Values present in the contact field will be added as segments. Values not present, but available in multiselect field, indicate segments to be removed.

### Author
Leuchtfeuer Digital Marketing GmbH

mautic@Leuchtfeuer.com
