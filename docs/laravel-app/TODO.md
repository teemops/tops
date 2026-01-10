# TODOs

I need to ensure that all documentation that referred to the old app architecture
is updated to now use the Laravel app specific architecture.

All documentation for features and user stories is still valid.

Remove all old wireframes from the root directory, we only have 

## Docs

Move all related docs under app folder and root folder to docs. 

If you need any docs for reference under the app folder, have one document and reference and link to the docs folder for additional information.

## Update of the app structure.

Removal of backend and frontend folders. This has already been done, but we need to remove any reference to this.

- Update Architecture to reflect our latest change to use only Laravel / Vue in our app folder.
- Update all associated readme.
- Update Progress

## Combine all previous practices into our product development process

Ensure that the Laravel app follows the practices document wherever possible.
Highlight any significant changes to the Laravel app before making any. For example if it requires us breaking out Vue and Laravel into separate apps we will not do this, because we want to have a monolith.

## Notifications
Overall in app system notifications to show at top of screen when logged in.
Use color schemes coloring background for info/warning/error messages.

## FEATURES

Ensure all new features in this todo document are put in the FEATURES_SPEC.md file that already exists. We want to keep everything in one place.

### User signup flow
OK few improvements on user table, signup and registration:

I'm going to add some user stories as follows. these need to update the docs 
As a user when I signup and get the verify email I need to have a message showing at top of the screen to say verified.
When I have verified user 