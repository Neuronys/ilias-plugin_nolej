# ![Icon](templates/images/icon_xnlj.svg) Nolej AI for ILIAS LMS
ILIAS integration plugin for [Nolej AI](https://nolej.io/).
A demo is available [on YouTube](https://www.youtube.com/watch?v=knCsFV4bjeY).

## Table of Contents
- [ Nolej AI for ILIAS LMS](#-nolej-ai-for-ilias-lms)
  - [Table of Contents](#table-of-contents)
  - [Introduction](#introduction)
  - [:globe\_with\_meridians: Supported languages](#globe_with_meridians-supported-languages)
  - [Requirements](#requirements)
  - [Installation](#installation)
    - [Download the plugin](#download-the-plugin)
    - [Install the plugin](#install-the-plugin)
    - [After installation](#after-installation)
    - [Page component companion plugin](#page-component-companion-plugin)


## Introduction
Nolej AI, developed by Neuronys, offers several advantages, including AI-driven
courseware that can quickly convert documents, videos, and audio into dynamic
active learning content. It facilitates skill development and personalized
learning paths, saving educators significant time and enhancing engagement through
interactive content creation tools, gamification, and social learning.

To use the plugin, start by [registering on Nolej AI](https://live.nolej.io/signup) website.
Upon registration, you'll receive an API key. Simply insert this key into the plugin to
enable its features. It's a quick step to enhance your experience. Let's get started! :rocket:

Please note that while the plugin itself is free, registration on Nolej website for
an API key is a separate process. Your support is appreciated!

## :globe_with_meridians: Supported languages
Help us expand language support! If your favorite language isn't here, contribute and make this project truly global.
Your input is not just welcome - it's essential! :rocket:

This plugin currently supports the following languages:

* :uk: English
* :fr: French
* :it: Italian
* :de: German

## Requirements
Note: this branch is for ILIAS 8. If you have ILIAS 6 or 7,
see [branch release_7](https://github.com/oc-group/Nolej-AI-for-ILIAS-LMS/tree/release_7).

* ILIAS 8.x
* [H5P Repository plugin](https://github.com/srsolutionsag/H5P) installed and updated (tested wih version `5.0.11`).

## Installation

### Download the plugin

From the ILIAS directory, run:

```sh
mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject
cd Customizing/global/plugins/Services/Repository/RepositoryObject
git clone https://github.com/oc-group/Nolej-AI-for-ILIAS-LMS.git Nolej
```

Return to the ILIAS directory and run:

```sh
composer du
```

### Install the plugin

1. Go into `Administration` -> `Extending ILIAS` -> `Plugins`
2. Look for the plugin "Nolej"
3. Click on `Actions` -> `Install`

### After installation

1. Configure the API Key
   1. Log in to ILIAS and navigate to `Administration` -> `Extending ILIAS` -> `Plugins`.
   2. Find the "Nolej" plugin in the list and click on `Actions` -> `Configure`.
   3. In the configuration section, locate the "API Key" field.
   4. Enter your Nolej API key obtained during registration.
   5. Click "Save" to apply the changes.

2. Enable the Anonymous Access
   1. Go into `Administration` -> `System Settings and Maintenance` -> `General Settings`
   2. Enable `Anonymous Access`
   3. Save

3. Enable TinyMCE Editor (optional, but recommended)
   1. Go into `Administration` -> `Layout and Navigation` -> `Editing`
   2. Select tab `TinyMCE Editor` -> `General Settings`
   3. Activate the checkbox
   4. Save

4. (Only for ILIAS 8) Open `client.ini.php` file inside the `data` directory and put this below `[server]`:

```
[server]
prevent_super_global_replacement = 1
```

### Page component companion plugin
Explore a personalized touch to your pages with the
[companion page component plugin](https://github.com/oc-group/Nolej-AI-for-ILIAS-LMS-Page-Component/)!
Seamlessly insert activities directly within the ILIAS page editor to tailor your
experience. This addition takes customization to the next level, providing a
more dynamic and engaging user interface. Get ready to elevate your content
creation with this powerful extension! :bulb: :sparkles:
