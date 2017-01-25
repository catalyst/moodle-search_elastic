[![Build Status](https://travis-ci.org/catalyst/moodle-search_elastic.svg?branch=master)](https://travis-ci.org/catalyst/moodle-search_elastic)

# Moodle Global Search - Elasticsearch Backend

This plugin allows Moodle to use Elasticsearch as the search engine for Moodle's Global Search.

## Installation
**NOTE:** Complete all of these steps before trying to enable the Global Search functionality in Moodle.

1. Get the code and copy/ install it to: `<moodledir>/search/engine/elastic`
2. Run the upgrade: `sudo -u www-data php admin/cli/upgrade` '''Note:''' the user may be different to www-data on your system.
3. Set up the plugin in *Site administration > Plugins > Search > Manage global search* by selecting *elastic* as the search engine.
4. Configure the Elasticsearch plugin at: *Site administration > Plugins > Search > Elastic*
    4.1 Set *hostname* and *port* of your Elasticsearch server
5. To create the index and populate Elasticsearch with your site's data, run this CLI script. `sudo -u www-data php search/cli/indexer.php --force`
6. Enable Global search in *Site administration > Advanced features*

## Elasticsearch Version Support
Currently this plugin is tested to work against the following versions of Elasticsearch:

* 2.3.4
* 2.4.4
* 5.1.2

## File Indexing Support
This plugin uses [Apache Tika](https://tika.apache.org/) for file indexing support. Tika parses files, extracts the text, and return it via a REST API.

### Tika Setup
Seting up a Tika test service is straight forward. In most cases on a Linux environment, you can simply download the Java JAR then run the service.
<pre><code>
wget http://apache.mirror.amaze.com.au/tika/tika-server-1.14.jar
java -jar tika-server-1.14.jar
</code></pre>

This will start Tika on the host. By default the Tika service is available on: `http://localhost:9998`

### Enabling File indexing support in Moodle
Once a Tika service is available the Elasticsearch plugin in Moodle needs to be configure for file indexing support.<br/>
Assuming you have already followed the basic installation steps, to enable file indexing support:

1. Configure the Elasticsearch plugin at: *Site administration > Plugins > Search > Elastic*
2. Select the *Enable file indexing* checkbox.
3. Set *Tika hostname* and *Tika port* of your Tika service. If you followed the basic Tika setup instructions the defaults should not need changing.
4. Click the *Save Changes* button.

### What is Tika
From the [Apache Tika](https://tika.apache.org/) website:
<blockquote>
The Apache Tika™ toolkit detects and extracts metadata and text from over a thousand different file types (such as PPT, XLS, and PDF). All of these file types can be parsed through a single interface, making Tika useful for search engine indexing, content analysis, translation, and much more. You can find the latest release on the download page. Please see the Getting Started page for more information on how to start using Tika.
</blockquote>

### Why use Tika as a standalone service?
It is common to see Elasticsearch implementations using an Elasticsearch file indexing plugin rather than a standalone service. Current Elasticsearch plugins are a wrapper arround Tika. (The Solr search engine also uses Tika).<br/>
Using Tika as a standalone service has the following advantages:

* Can support file indexing for Elasticsearch setups that don't support file indexing plugins such as AWS.
* No need to chagne setup or plugins based on Elasticsearch version.
* You can share one Tika service across multiple Elasticsearch clusters.
* Can run Tika on dedicated infrastrucutre that is not part of your search nodes.

## Test Setup
In order to run the PHP Unit tests for this plugin you need to setup and configure an Elasticsearch instance as will as supply the instance details to Moodle.
You need to define:

* Hostname: the name URL of the host of your Elasticsearch Instance
* Port: The TCP port the host is listening on
* Index: The name of the index to use during tests. **NOTE:** Make sure this is different from your production index!

### Setup via config.php
To define the required variables in via your Moodle configuration file, add the following to `config.php`:
<pre><code>
define('TEST_SEARCH_ELASTIC_HOSTNAME', 'http://127.0.0.1');
define('TEST_SEARCH_ELASTIC_PORT', 9200);
define('TEST_SEARCH_ELASTIC_INDEX', 'moodle_test_2');
</pre></code>

### Setup via Environment variables
The required Elasticserach instance configuration variables can also be provided as environment variables. To do this at the Linux command line:
`export TEST_SEARCH_ELASTIC_HOSTNAME=http://127.0.0.1; export TEST_SEARCH_ELASTIC_PORT=9200; export TEST_SEARCH_ELASTIC_INDEX=moodle_test`
