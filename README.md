# Radio Localized Drupal 9 Site
This is a work-in-progress D9 site I am developing to support my radio show, Radio Localized, broadcast weekly on KBFG-FM in Seattle.  The radio show itself features a different location each episode.  The purpose of this site is to function as an extension of the show, displaying the places represented by each episode on an interactive map.


## Repository and Demo
This repo resides at https://github.com/bixgomez/radiolocalized

You can see it in action at http://radiolocalized.fezziwigmedia.com

## Notes
As you will see, the site itself is currently under construction.  There is no landing page as of yet, and I have
yet to upload data for all episodes.  However, it should give you a good idea of the sort of functionality I am
trying to achieve.


* **Built on Drupal, hosted on Linode**
  * This site was built on the latest release of Drupal 9
  * Core, modules and dependencies were all installed using Composer
  * I am currently hosting this site on a Linode VPS I built from scratch, running Ubuntu 16.04 LTS


* **Front End Notes**
  * This site features an entirely custom built theme, a modified
    version of the theme I built for my (Drupal 8) portfolio
    web site at http://richardgilbert.co
  * My preferred workflow is Gulp with LiveReload.  I also enjoy
    BrowserSync, but as I began this theme ages ago, I have decided to maintain
    its original workflow.
  * I have endeavored to keep all node modules up to date.
  * The Sass structure is what I would call "semi-atomic": I don't stick to the
    strict method proposed by Brad Frost, but I do take some cues from its top-down
    modular structure.


* **The Maps!**
  * As an urban planning student and general enthusiast, I love maps.  My graduate
    thesis project included an early (2003) JavaScript-based map comparison tool, which
    you can visit at https://deansluyter.com/rainieratlantic/
  * With this site, I am using Leaflet Maps and the Google API to display locations
    specific to each song featured on each episode.
  * You will find all of the JavaScript I wrote to support this functionality is in the theme.

## To-Dos
As this is a personal project, I am writing specs "on the go", experimenting with ideas and reëvaluating decisions on the fly.  Working like this, issues arise constantly.  So...

* Further work on landing page, obviously.
* Design and develop individual song page template
* Main menu is unsustainable - this basic menu will only accommodate about 10 episodes before it becomes unmanageable
* Embed a Mixcloud player for each episode (and a link to the Mixcloud account as a whole)
* Clean up the site header
  * add blocks for info that will appear on the home page only
  * account for lack of episode information on non-episode pages.
* Resolve mobile issues
  * Episode pages are a bit "tight" with the 60/40 split
  * 100vh height does not seem to work on iPhone Safari or Chrome (seems to be due to the height of the search/address bar.
* Reconsider early decision to use Layout Builder for each individual song teaser. These should be simple cards with much cleaner markup.
