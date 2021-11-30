
# webtrees module hh_clippings_cart_enhanced

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.0.x-green)
![Latest Release](https://img.shields.io/github/v/release/hartenthaler/hh_clippings_cart_emhanced)

This [webtrees](https://www.webtrees.net/) custom module replaces the original 'Clippings Cart' module.
It offers additional possibilities to add records to the clippings cart
and adds beside the possibility to export a GEDCOM zip-file the possibility
to visualize the records in the clippings cart using a diagram.

!!! This is a alpha version! Do not use it in a productive webtrees system! !!!

## Contents
This Readme contains the following main sections

* [Description](#description)
* [Screenshots](#screenshots)
* [Requirements](#requirements)
* [Installation](#installation)
* [Upgrade](#upgrade)
* [Translation](#translation)
* [Contact Support](#support)
* [Thank you](#thanks)
* [License](#license)

<a name="description"></a>
## Description

This custom module replaces the original 'Clippings Cart' module.
The design concept for the enhanced clippings cart is shown in the following diagram:
<p align="center"><img src="resources/docu/ClippingsCartEnhanced.png" alt="new concept for enhanced clippings cart" align="center" width="100%"></p>

Various functions can collect records in the clippings cart and these records could then be passed on to various evaluation / export / visualization functions.
The user of the module can decide which records should be sent to the clippings cart
and which action should be executed on these records.

As input functions there are
* the functions of the current module "clippings cart" or the existing custom [Vesta clippings cart](https://github.com/vesta-webtrees-2-custom-modules/vesta_clippings_cart) module, which offer the possibility to put records in the clippings cart for a person or another GEDCOM object like a source or a note.
This includes for a person for example their ancestors or descendants,
possibly with their families. For a location it includes for example all persons with reference to this place.
* the search in the control panel for unconnected persons in a tree (with a new button "send to clippings cart") (tbd)
* the normal webtrees search (also with a button "send to clippings cart"), so that you can search for anything you want and with the option of using all the filter functions that are currently offered (tbd)
* the list display modules "Families" and "Family branches", so that you can send all persons with the same family name or all persons from a clan to the clippings cart (tbd)
* new functions in this module searching for marriage chains or ancestor circles in the tree.

The user can then delete selected records or groups of records of the same type
in the clippings cart or delete the clippings cart completely.

An action initiated by the user then takes place on the records in the clippings cart, such as
* the export to a GEDCOM zip file, as in the actual clippings cart module
* the display of the objects in list form with the possibility of sorting and filtering this list (tbd)
* the transfer of the records in the clippings cart to new functions that visualize this data or analyze it statistically.
Such a function could be for example [TAM](https://github.com/rpreiner/tam) (Topographic Attribute Map) or [Lineage](https://github.com/huhwt/lineage).

<a name="screenshots"></a>
## Screenshots

Screenshot of the new menu
<p align="center"><img src="resources/docu/Screenshot_Menu.png" alt="Screenshot of main menu" align="center" width="80%"></p>

Screenshot of menu to add global sets of records for a tree
<p align="center"><img src="resources/docu/Screenshot_ViewAddGlobal.png" alt="Screenshot of menu to add global sets of records" align="center" width="80%"></p>

Screenshot of new menu to delete records in the clippings cart
<p align="center"><img src="resources/docu/Screenshot_ViewEmpty.png" alt="Screenshot of menu to delete records" align="center" width="80%"></p>

Screenshot of new menu to execute actions on the records in the clippings cart
<p align="center"><img src="resources/docu/Screenshot_ExecuteActions.png" alt="Screenshot of menu to execute actions" align="center" width="80%"></p>

Screenshot using TAM for a tree with more than 10.000 persons
<p align="center"><img src="resources/docu/Screenshot_Tree.png" alt="Screenshot of large tree" align="center" width="80%"></p>

Screenshot using TAM for a visualisation of all ancestor circles in this tree
(removing all leaves in this tree recursively)
<p align="center"><img src="resources/docu/Screenshot_Circles.png" alt="Screenshot of circles" align="center" width="80%"></p>

Screenshot using TAM to show a H diagram (comparable to webtrees chart "compact tree")
<p align="center"><img src="resources/docu/Screenshot_H_diagram.png" alt="Screenshot of H diagram" align="center" width="80%"></p>

Screenshot using TAM for a partner chain with 30 partners of partners of partners ...
<p align="center"><img src="resources/docu/Screenshot_PartnerChains.png" alt="Screenshot of partner chains" align="center" width="80%"></p>

<a name="requirements"></a>
## Requirements

This module requires **webtrees** version 2.0 or later.
This module has the same requirements as [webtrees#system-requirements](https://github.com/fisharebest/webtrees#system-requirements).

This module was tested with **webtrees** 2.0.17 version and all available themes and all other custom modules.
If you are using the Vesta clippings cart module: the integration is an open issue.

<a name="installation"></a>
## Installation

This section documents installation instructions for this module.

1. Download the [latest release](https://github.com/hartenthaler/hh_clippings_cart_enhanced/releases/latest).
3. Unzip the package into your `webtrees/modules_v4` directory of your web server.
4. Rename the folder to `hh_clippings_cart_enhanced`. It's safe to overwrite the respective directory if it already exists.
5. Login to **webtrees** as administrator, go to <span class="pointer">Control Panel/Modules/Genealogy/Menus</span>,
   and find the module. It will be called "Clippings cart enhanced". Check if it has a tick for "Enabled".
6. Edit this entry to set the access level for each tree and to position the menu item to suit your preferences.
7. You can deactivate the standard module "clippings cart".
8. Finally, click SAVE, to complete the installation.

<a name="upgrade"></a>
## Upgrade

To update simply replace the hh_clippings_cart_enhanced files
with the new ones from the latest release.

<a name="translation"></a>
## Translation

You can help to translate this module.
It uses the po/mo system.
You can contribute via a pull request (if you know how) or by e-mail.
Updated translations will be included in the next release of this module.

There are now, beside English and German, no other translations available.

<a name="support"></a>
## Support

<span style="font-weight: bold;">Issues: </span>you can report errors raising an issue in this GitHub repository.

<span style="font-weight: bold;">Forum: </span>general webtrees support can be found at the [webtrees forum](http://www.webtrees.net/)

<a name="thanks"></a>
## Thank you
Special thanks to [huhwt](https://github.com/huhwt/) for testing and suggestions.


<a name="license"></a>
## License

This module is derived from the [Vesta clippings cart](https://github.com/vesta-webtrees-2-custom-modules/vesta_clippings_cart) module.

* Copyright (C) 2021 Hermann Hartenthaler
* Copyright (C) 2021 Richard Cissée. All rights reserved.
* Derived from **webtrees** - Copyright 2021 webtrees development team.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

* * *
