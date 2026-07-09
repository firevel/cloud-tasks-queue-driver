# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel queue driver package that integrates Google Cloud Tasks as a queue backend. It allows Laravel applications running on Google App Engine or Google Cloud Run to use Cloud Tasks for job processing.

## Architecture

The package implements Laravel's queue contract through these key components:

- **CloudTasksServiceProvider** - Registers the queue connector, CloudTasksClient singleton, and the task handler route
- **CloudTasksConnector** - Laravel queue connector that creates CloudTasksQueue instances
- **CloudTasksQueue** - Implements Laravel's Queue contract; uses CloudTasksService to push jobs to Cloud Tasks
- **CloudTasksService** - Core service that creates and pushes tasks to Google Cloud Tasks API, handles both App Engine (AppEngineHttpRequest) and Cloud Run (HttpRequest) routing
- **CloudTasksJob** - Wraps the job payload received from Cloud Tasks, tracks retry attempts via X-AppEngine-TaskRetryCount header
- **CloudTasksController** - HTTP endpoint that receives task callbacks from Cloud Tasks and processes them through Laravel's queue worker
- **SignatureService** - HMAC-SHA256 signature generation/verification using Laravel's app.key for request authentication
- **CloudTasksRequest** - Form request that validates incoming task requests via signature verification

## Request Flow

1. Job dispatched via Laravel's queue API -> CloudTasksQueue::push()
2. CloudTasksService creates a Cloud Task with signed payload
3. Cloud Tasks calls back to the application at the configured route
4. CloudTasksController receives request, CloudTasksRequest validates signature
5. Job is processed through Laravel's queue worker

## Configuration

Queue connection config in `config/queue.php`:
- `driver`: 'cloudtasks'
- `service`: GAE service name (optional, defaults to GAE_SERVICE env)
- `version`: GAE version (optional, defaults to GAE_VERSION env)
- `route`: Handler endpoint path (default: /_cloudtasks)
- `project`: Google Cloud project ID
- `location`: Cloud Tasks location (must match App Engine region)
- `queue_name`: Default queue name
- `url`: Custom URL for Cloud Run (overrides auto-detected host)

## Dependencies

- `google/cloud-tasks`: ^1.8
- Laravel/Illuminate framework (queue, routing, container components)
