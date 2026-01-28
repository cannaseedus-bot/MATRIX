/**
 * PS-DSL Command Registry v1
 *
 * Deny-by-default PowerShell command allowlist.
 * Only actions in the `allow` map can be executed.
 *
 * XCFE Class: host.powershell.registry
 */

export const PS_COMMAND_REGISTRY = Object.freeze({
  // === ALLOWLIST ===
  // Only these actions can be lowered to PowerShell
  allow: Object.freeze({
    // Process management (read-only)
    "process.list": {
      cmdlet: "Get-Process",
      params: [],
      description: "List running processes"
    },
    "process.query": {
      cmdlet: "Get-Process",
      params: ["name", "id"],
      description: "Query specific process"
    },

    // Service management (read-only)
    "service.list": {
      cmdlet: "Get-Service",
      params: [],
      description: "List all services"
    },
    "service.query": {
      cmdlet: "Get-Service",
      params: ["name", "status"],
      description: "Query service by name or status"
    },

    // Event log (read-only)
    "eventlog.list": {
      cmdlet: "Get-EventLog",
      params: ["logname"],
      description: "List event log entries"
    },
    "eventlog.query": {
      cmdlet: "Get-EventLog",
      params: ["logname", "newest", "entrytype"],
      description: "Query event log with filters"
    },

    // System info (read-only)
    "computer.info": {
      cmdlet: "Get-ComputerInfo",
      params: [],
      description: "Get computer information"
    },
    "host.info": {
      cmdlet: "Get-Host",
      params: [],
      description: "Get PowerShell host info"
    },

    // Disk info (read-only)
    "disk.list": {
      cmdlet: "Get-PSDrive",
      params: [],
      description: "List drives"
    },

    // Network (read-only)
    "network.adapters": {
      cmdlet: "Get-NetAdapter",
      params: [],
      description: "List network adapters"
    },
    "network.connections": {
      cmdlet: "Get-NetTCPConnection",
      params: ["state"],
      description: "List TCP connections"
    },

    // Date/Time (read-only)
    "date.get": {
      cmdlet: "Get-Date",
      params: ["format"],
      description: "Get current date/time"
    },

    // Environment (read-only)
    "env.list": {
      cmdlet: "Get-ChildItem",
      params: [],
      description: "List environment variables",
      prefix: "Env:"
    }
  }),

  // === HARD DENY ===
  // These cmdlets are NEVER allowed, even if allowlisted
  deny: Object.freeze([
    // Execution
    "Invoke-Expression",
    "iex",
    "Invoke-Command",
    "Start-Process",
    "Start-Job",

    // Code loading
    "Add-Type",
    "New-Object",
    "Import-Module",

    // File system mutation
    "Set-Content",
    "Add-Content",
    "Remove-Item",
    "New-Item",
    "Copy-Item",
    "Move-Item",
    "Rename-Item",

    // Registry mutation
    "Set-ItemProperty",
    "Remove-ItemProperty",
    "New-ItemProperty",

    // Network
    "Invoke-WebRequest",
    "Invoke-RestMethod",
    "curl",
    "wget",
    "Send-MailMessage",

    // Credential/Security
    "Get-Credential",
    "ConvertTo-SecureString",
    "Export-Clixml",

    // System mutation
    "Stop-Process",
    "Stop-Service",
    "Start-Service",
    "Restart-Service",
    "Set-Service",
    "Stop-Computer",
    "Restart-Computer",

    // Scheduled tasks
    "Register-ScheduledTask",
    "Set-ScheduledTask",

    // PowerShell remoting
    "Enter-PSSession",
    "New-PSSession",
    "Invoke-Command"
  ])
});

/**
 * Check if an action is allowlisted
 */
export function isActionAllowed(action) {
  return action in PS_COMMAND_REGISTRY.allow;
}

/**
 * Check if a cmdlet is denied
 */
export function isCmdletDenied(cmdlet) {
  return PS_COMMAND_REGISTRY.deny.includes(cmdlet);
}

/**
 * Get action specification
 */
export function getActionSpec(action) {
  return PS_COMMAND_REGISTRY.allow[action] || null;
}
