# GPT3 CRM Patch 16

Apply this patch on top of the latest GPT3 CRM build.

## Configuration
Database: `gpt3`
Project folder: `C:\xampp\htdocs\gpt3`

## New operational areas
- Service workflow execution lifecycle
- Workflow step execution UI
- Communication queue foundation for missing requirements
- Additional workflow execution database safety
- GPT3-specific routes

## Apply
1. Copy the changed/new PHP files into `C:\xampp\htdocs\gpt3`.
2. Import `database/16_next_stage_operations.sql` into the `gpt3` database.
3. Restart the PHP server.
4. Open the CRM and test a project workflow.
