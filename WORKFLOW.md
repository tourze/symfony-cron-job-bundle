# Cron Job Execution Workflow

This document illustrates the workflow for registering, scheduling, and executing cron jobs in the Symfony Cron Job Bundle.

```mermaid
flowchart TD
    A[Register Cron Job (Attribute or Provider)] --> B[Add Crontab Entry (cron-job:add-cron-tab)]
    B --> C[System crontab triggers cron:run every minute]
    C --> D[cron:run Command]
    D --> E{Find due Cron Jobs}
    E -- Attribute Jobs --> F[Dispatch via Messenger]
    E -- Provider Jobs --> F
    F --> G[Command Executed Asynchronously]
    G --> H[Job Complete]
```

## Steps Explained

1. **Register Cron Job**: Use PHP attribute or implement provider interface.
2. **Add Crontab Entry**: Use built-in command to register the main entry.
3. **Crontab Triggers**: System crontab runs the Symfony command every minute.
4. **Find Due Jobs**: The bundle checks which jobs are due based on cron expressions.
5. **Dispatch**: Due jobs are dispatched asynchronously via Messenger.
6. **Execution**: Jobs are executed; output and status can be monitored.
