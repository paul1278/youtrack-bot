# YouTrack-Bot
This is a super simple implementation of a small bot for YouTrack.
It solves the *issue* of creating scheduled issues on YouTrack.

The interface provides a simple configuration using YAML-files to define all your fields and to use them easily on tickets you might want to create.

## Requirements
* PHP 7 / 8
* php-curl
* php-yaml
* Some scheduler like `cron` - or even web-based.

On Ubuntu, you can install everything with:
```
apt install php php-curl php-yaml cron
```

## Installation
* Extract the latest version of this project.
* Copy `config.dist.php` to `config.php` and edit the needed values.
* Copy the examples from the `examples`-folder, remove the `.dist` from their names and edit them accordingly to the setup-process below.

## Setup
The configuration of this bot consists of three or more files.
The most important one is `config.php`. This file is mostly for connecting with your YouTrack-instance and debugging.
|Variable|Description|
|---|---|
|`YOUTRACK_URL`|The main URL to your instance. Without any API-paths etc.|
|`YOUTRACK_TOKEN`|The token you got from YouTrack for communicating with the API.|
|`TICKET_FILE`|Main location of the `tickets.yml`-file. Needed for creating tickets.|
|`CURL_ALLOW_SELF_SIGNED`|Set to true if you have a self-signed TLS-certificate on your YouTrack-instance.|
|`DRY_RUN`|If set to true, the bot will only display the payloads it would send to the API of your server, but does not send it.|

### Definition of fields
YouTrack allows you to create custom fields on tickets. Those fields can be mapped in the file `fields.yml`. There always must be this file right beside `main.php` in the same folder.
The syntax is quite simple as it is basically a template for the REST-API, just structured in YAML:
```yaml
fields:
  <internal_name_for_bot>:
    $type: <type>
    name: <internal_name_of_field>
    value: '{{value}}'
```
The `{{value}}` is a placeholder variable which will be replaced by the bot. If you don't know the correct structure of some custom field, just have a look at the documentation of YouTrack. TODO: Explain using DevTools.

### Creating tickets
After you created your fields, you can create a `tickets.yml`-file. Inside this file, you can specify tickets the bot should create:

```yaml
tickets:
  - summary: <Summary inside YouTrack, required>
    description: |
      <Description inside YouTrack, required but can be empty>
    project: <project-id, e.g. 0-1>
    <internal_name_for_bot>: somevalue
    <internal_name_for_bot>: somevalue2
    [...]
```
Also, there is a simple way to calculate due-dates of tickets, which can be specified in this file. Assume we have the following field on our `fields.yml`:
```yaml
fields:
  duedate:
    $type: DateIssueCustomField
    id: "523-0"
    value: '{{value}}'
```
This is a field to basically set the due-date of our ticket, and we always want it to be the last day of the current month. For that, we can specify the date `eom` (End-Of-Month):
```yaml
tickets:
  - summary: "Test"
    description: |
      This is a small ticket
    project: 0-1
    assignee: my-user
    duedate: "{{eom}}"

dates:
  eom:
    script:
      - "last day of this month"
    format: "U"
    milliseconds: true
```
The `script` will be directly applied to the current day and time using the [`DateTime::modify`-function](https://www.php.net/manual/en/datetime.modify.php) of PHP.
You could use `{{eom}}` even in other fields as part of the text, thats why you can `format` it as you want using [supported Date and Time Formats](https://www.php.net/manual/en/datetime.formats.php).
If you want to have a UNIX-timestamp (which is needed in case of the due-date), then simply use `U` (UNIX-timestamp in seconds) and set `milliseconds: true`, this will convert the formatted number to an integer and multiply it with `1000` - needed on this field.

After you created all those files, you can simply run the bot with `php main.php`.

### Running multiple `tickets.yml`
You can automate the bot using e.g. `cron`:
```
# Runs on the first day of each month at midnight.
0 0 1 php <location>/main.php
```

You can separate all tickets as much as you want. For example, you want weekly and monthly tickets.
To override the used `tickets.yml`-file while running, use the environment-variable `TICKET_FILE`:

```
# Runs on the first day of each month at midnight.
0 0 1 * * TICKET_FILE=/home/user/tickets-monthly.yml php <location>/main.php

# Runs at midnight on mondays
0 0 * * 1 TICKET_FILE=/home/user/tickets-weekly.yml php <location>/main.php
```


---
&copy; paul1278

