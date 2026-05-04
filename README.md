## Multiselect Handling by Leuchtfeuer
Enhanced Mautics ability to handle the fieldtypes “Select” & “Multiselect”. Handling of fieldtype "Select" was introduced in later version, thus the naming "multiselect".


## Requirements for this release
> [!TIP]
> Other releases of this plugin may cover different Mautic versions!
- Mautic 5.x (min. 5.1)

## Installation
### Composer
This plugin can be installed through composer.
### Manual Installation
Alternatively, it can be installed manually, following the usual steps:
- Download the plugin
- Unzip to the Mautic `plugins` directory
- Rename folder to `BundleName`
- In the Mautic backend, go to the `Plugins` page as an administrator
- Click on the `Install/Upgrade Plugins` button to install the Plugin.
OR
- If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.

## Configuration

## Role Permissions

## Usage

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

### Campaign action: “Change Segment membership based contact field”
1. Select managed select or multiselect field
2. Contacts will be added/removed to/from segments corresponding to the chosen select / multiselect field values


Add or remove segments based on this contacts “select” or “multiselect” field values. Values present in the contact field will be added as segments. Values not present, but available in multiselect field, indicate segments to be removed.
## API support
## Known Issues
List any current issues or limitations.
## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.
If things are still funny, please try
`php bin/console cache:clear`

## Change log
- https://github.com/Leuchtfeuer/`bundle-name`/releases
## Future Ideas
Mention any planned updates, features, or ideas for future development.
## Sponsoring & Commercial Support
We are continuously improving our plugins. If you are requiring priority support or custom features, please contact us at mautic-plugins@leuchtfeuer.com.
## Get Involved
Feel free to open issues or submit pull requests on [GitHub](#). Follow the contribution guidelines in `CONTRIBUTING.md`.”
## Credits
@beetofly
@biozshock
@ekkeguembel
@lenonleite
@LeonOltmanns
@PatrickJenkner
## Author
Leuchtfeuer Digital Marketing GmbH
Please raise any issues in GitHub.
For all other things, please email mautic-plugins@Leuchtfeuer.com
## License
This plugin is licensed under the GPL v3 License.
## Resources / Further Readings
Provide links to any related resources or further readings.
