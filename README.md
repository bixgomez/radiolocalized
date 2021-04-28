# Radio Localized Drupal 9 Site
This is a work-in-progress D9 site to support my radio show, Radio Localized, broadcast weekly on KBFG-FM in Seattle.


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




