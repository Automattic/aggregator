# Aggregator

Synchronise posts between blogs in a multisite network.

## Basic Usage

Network activate the plugin and you'll find a new options screen in Network Admin > Settings > Aggregator.

Here you can create what we call "aggregator jobs". An aggregator job describes a single portal and source, and what should be pushed between them. You can create multiple aggregator jobs for each portal and source, but you can't have multiple jobs describing the same connection.

For example, if I have two sites, I can create a maximum of two jobs;

Portals | Sources
------- | -------
Site A  | Site B
Site B  | Site A

You can't do this;

Portals | Sources
------- | -------
Site A  | Site B
Site B  | Site A
Site B  | Site A

The second and third jobs here conflict with each other. This is open to change, if you want to send in a pull request.

### Job settings

When you create an aggregator job, you choose the post types, taxonomies and terms you want to sync. Only posts that match the settings will be synced. Here's some examples;

Job | Post Types | Taxonomies       | Terms                            | Outcome
--- | ---------- | ---------------- | -------------------------------- | -------
A   | Posts      | Categories       | [None]                           | All posts will be pushed along with *any* assigned categories, tags will be ignored.
B   | Posts      | Categories       | Categories: News, Reviews        | Only posts that belong in *either* the News or Reviews categories will be pushed. Tags ignored.
C   | Posts      | Categories, Tags | [None]                           | All posts will be pushed along with any assigned categories and tags.
D   | Posts      | Categories, Tags | Categories: News; Tags: Featured | Only posts in the News category *and* tagged 'Featured' will be pushed.

You'll also need set an "author". This is the author on the *portal* to whom pushed posts will be assigned. I.e., the plugin doesn't check if a post's author exists on the portal and retains the association. Instead, each post that is pushed will be given the same author.

## Technical stuff

### Class: `Aggregate`

Sets up the plugin, creating the admin area and some other bits and bobs.

### Class: `Aggregator_Job`

This represents the aggregator jobs you create in the network admin. To create/retrieve an Aggregator_Job object, the class is called with the IDs of the portal and source blogs as parameters. The Class does all the hard work of retrieving the settings so that they can be checked during a 'push'.

### Class: `Aggregate`

This is where the pushing of posts actually takes place. The `push_post_data_to_blogs` function starts the grunt work, retrieving the Aggregator_Job object and checking if the saved post should be pushed based on the job settings.

### Classes: `Aggregator_Jobs_List_tables` and `Aggregator_Portals_List_table`

These two are just extensions of the WP_List_Table class and create the tables you see in Network Admin.

### Site Options

There are a couple of pretty important site options (as in [get_site_option](http://codex.wordpress.org/Function_Reference/get_site_option)) that the plugin relies on.

* `aggregator_{$blog_id}_source_blogs` - Stores a simple array of blog IDs. In this case, the IDs of blogs that posts will be pushed *from* where $blog_id is a portal
* `aggregator_{$blog_id}_portal_blogs` - Vice versa above, so $blog_id is the ID of a source blog, and the value is an array portal blog IDs.

### Filters

There are some strategically placed filters that you can use to overwrite some of Aggregator's behaviour. These are well documented inline, so they are listed below and linked to the code.

* `aggregator_sync_meta_key` - [class-aggregate.php:91-102](Class_Aggregate#L91-102) - Decide whether to push some meta data to a portal site.
* `aggregator_allowed_post_types` - [class-aggregate.php:118-127](Class_Aggregate#L118-127) - Override the allowed post types as set by a sync job.
* `aggregator_taxonomy_terms` - [class-aggregate.php:167-173](Class_Aggregate#L167-173) - Allow overriding of non-whitelisted taxonomies and terms.
* `aggregator_taxonomy_terms` - [class-aggregate.php:243-249](Class_Aggregate#L243-249) - Allow overriding of non-whitelisted taxonomies and terms.
* `aggregator_orig_post_data` - [class-aggregate.php:462-470](Class_Aggregate#L462-470) - Alter the post data before syncing.
* `aggregator_orig_meta_data` - [class-aggregate.php:498-507](Class_Aggregate#L498-507) - Alter the meta data before syncing.
* `aggregator_portal_blogs` - [class-aggregator.php:217-226](Class_Aggregator#L217-226) - Filters the list of blogs to push to.
* `aggregator_source_blogs` - [class-aggregator.php:247-255](Class_Aggregator#L247-255) - Filters the list of blogs to push from.

## Step-by-step instructions

Pushing posts to the national site is done using a plugin called Aggregator. Here's how to set that up:

1. Log in to WordPress
1. Hover over "My Sites" in the top left to reveal the sites menu, and navigate to Network Admin
1. In Network Admin, under Settings, choose "Aggregator"
1. Click "Add New"
1. From the first drop down, choose the site you wish posts to be pushed to – e.g. the national site.
1. From the second, choose the source of posts
1. You will now be able to set the criteria for this "sync job" - this works on a whitelist system. E.g., you choose the post types, taxonaomies and terms that should be pushed. Anything that isn't selected won't be pushed.
1. Select your preferred post types, taxonomies, and terms
1. Click Save