## Leuchtfeuer Multiselect Handling

1. Install plugin.
2. Folder Name is MauticMultiselectHandlingBundle.

### Form Action

1. Set up custom field(s) with multiselect or select type, which will contain a list of Segments for synchronization. Needs to have the same alias.
2. Add form action "Modify Segment Membership based on Multiselect".

After form is submitted the Contact will be added/removed to/from segments according to the multiselect or select value(s) selected in the form action.

### Campaign action "Change contact's multiselect field"

1. Select managed multiselect field.
2. Select values to add to multiselect field. In case this value is empty no values will be added.
3. Select values to remove from multiselect field. In case this value is empty no values will be removed.

### Campaign action "Change contact's select field"

1. Select managed select field.
2. Select value to set as the value of a select field. In case this value is empty the value will be not set by this action.
3. Select values to remove from a select field. In case this value is empty no values will be removed.

### Author

Leuchtfeuer Digital Marketing GmbH

mautic@Leuchtfeuer.com
