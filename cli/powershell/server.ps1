<#
.SYNOPSIS
    MX2LM Static Server - KUHUL-governed PowerShell HTTP server

.DESCRIPTION
    A lightweight static file server and API endpoint for MX2LM CLI.
    Governed by KUHUL π collapse - PowerShell acts as bounded actuator.

.PARAMETER Port
    HTTP port to listen on (default: 8080)

.PARAMETER Root
    Root directory for static files (default: ./public)

.PARAMETER ApiEnabled
    Enable API endpoints (default: true)

.EXAMPLE
    .\server.ps1 -Port 8080 -Root "./public"
#>

param(
    [int]$Port = 8080,
    [string]$Root = "./public",
    [bool]$ApiEnabled = $true,
    [string]$ConfigPath = "./server.config.json"
)

# ═══════════════════════════════════════════════════════════════
# KUHUL CLASS: api.local.static
# Domain: host | Scope: localhost | Authority: read-only
# ═══════════════════════════════════════════════════════════════

$script:ServerState = @{
    StartTime = $null
    RequestCount = 0
    Healthy = $true
    PiSupport = 1.0
}

# MIME types mapping
$MimeTypes = @{
    ".html" = "text/html; charset=utf-8"
    ".htm"  = "text/html; charset=utf-8"
    ".css"  = "text/css; charset=utf-8"
    ".js"   = "application/javascript; charset=utf-8"
    ".json" = "application/json; charset=utf-8"
    ".xml"  = "application/xml; charset=utf-8"
    ".txt"  = "text/plain; charset=utf-8"
    ".md"   = "text/markdown; charset=utf-8"
    ".png"  = "image/png"
    ".jpg"  = "image/jpeg"
    ".jpeg" = "image/jpeg"
    ".gif"  = "image/gif"
    ".svg"  = "image/svg+xml"
    ".ico"  = "image/x-icon"
    ".woff" = "font/woff"
    ".woff2"= "font/woff2"
    ".ttf"  = "font/ttf"
    ".eot"  = "application/vnd.ms-fontobject"
    ".pdf"  = "application/pdf"
    ".zip"  = "application/zip"
}

function Get-MimeType {
    param([string]$Extension)
    if ($MimeTypes.ContainsKey($Extension)) {
        return $MimeTypes[$Extension]
    }
    return "application/octet-stream"
}

function Get-ServerStatus {
    $uptime = if ($script:ServerState.StartTime) {
        [int]((Get-Date) - $script:ServerState.StartTime).TotalSeconds
    } else { 0 }

    return @{
        service = "MX2LM Static Server"
        version = "1.0.0"
        status = "ok"
        uptime = $uptime
        requests = $script:ServerState.RequestCount
        healthy = $script:ServerState.Healthy
        pi_support = $script:ServerState.PiSupport
        port = $Port
        root = (Resolve-Path $Root -ErrorAction SilentlyContinue)
        api_enabled = $ApiEnabled
        kuhul_class = "api.local.static"
    } | ConvertTo-Json -Depth 3
}

function Get-HealthCheck {
    return @{
        healthy = $script:ServerState.Healthy
        timestamp = (Get-Date -Format "o")
    } | ConvertTo-Json
}

function Get-AgentsList {
    return @{
        agents = @(
            @{ name = "claude"; type = "cli"; package = "@anthropic-ai/claude-code" }
            @{ name = "codex"; type = "cli"; package = "codex" }
            @{ name = "aider"; type = "cli"; package = "aider-chat" }
            @{ name = "ollama"; type = "api"; host = "http://localhost:11434" }
            @{ name = "ollama-cloud"; type = "api"; host = "https://ollama.com" }
        )
        kuhul_class = "api.static"
    } | ConvertTo-Json -Depth 3
}

function Get-OllamaModels {
    $models = @{
        local = @(
            @{ name = "llama3.2"; size = "2B"; type = "general" }
            @{ name = "llama3.2:3b"; size = "3B"; type = "general" }
            @{ name = "codellama"; size = "7B"; type = "code" }
            @{ name = "mistral"; size = "7B"; type = "general" }
            @{ name = "mixtral"; size = "47B"; type = "general" }
        )
        cloud = @{
            frontier = @(
                @{ name = "deepseek-v3.1:671b-cloud"; size = "671B" }
                @{ name = "gpt-oss:120b-cloud"; size = "120B" }
                @{ name = "kimi-k2:1t-cloud"; size = "1T" }
                @{ name = "qwen3-coder:480b-cloud"; size = "480B" }
                @{ name = "qwen3-vl:235b-cloud"; size = "235B" }
            )
            general = @(
                @{ name = "glm-4.6:cloud" }
                @{ name = "glm-4.7" }
                @{ name = "minimax-m2.1" }
                @{ name = "gemini-3-flash-preview" }
            )
            agentic = @(
                @{ name = "nemotron-3-nano"; size = "30B" }
                @{ name = "devstral-small-2"; size = "24B" }
                @{ name = "rnj-1"; size = "8B" }
            )
        }
        kuhul_class = "api.static"
    }
    return $models | ConvertTo-Json -Depth 4
}

function Send-Response {
    param(
        [System.Net.HttpListenerResponse]$Response,
        [string]$Content,
        [string]$ContentType = "application/json",
        [int]$StatusCode = 200
    )

    $Response.StatusCode = $StatusCode
    $Response.ContentType = $ContentType
    $Response.Headers.Add("Access-Control-Allow-Origin", "*")
    $Response.Headers.Add("Access-Control-Allow-Methods", "GET, OPTIONS")
    $Response.Headers.Add("Access-Control-Allow-Headers", "Content-Type")
    $Response.Headers.Add("X-KUHUL-Class", "api.local.static")

    $buffer = [System.Text.Encoding]::UTF8.GetBytes($Content)
    $Response.ContentLength64 = $buffer.Length
    $Response.OutputStream.Write($buffer, 0, $buffer.Length)
    $Response.OutputStream.Close()
}

function Send-File {
    param(
        [System.Net.HttpListenerResponse]$Response,
        [string]$FilePath
    )

    if (-not (Test-Path $FilePath)) {
        Send-Response -Response $Response -Content '{"error": "Not Found"}' -StatusCode 404
        return
    }

    $extension = [System.IO.Path]::GetExtension($FilePath).ToLower()
    $contentType = Get-MimeType -Extension $extension

    $Response.StatusCode = 200
    $Response.ContentType = $contentType
    $Response.Headers.Add("Access-Control-Allow-Origin", "*")
    $Response.Headers.Add("X-KUHUL-Class", "api.local.static")

    $fileBytes = [System.IO.File]::ReadAllBytes($FilePath)
    $Response.ContentLength64 = $fileBytes.Length
    $Response.OutputStream.Write($fileBytes, 0, $fileBytes.Length)
    $Response.OutputStream.Close()
}

function Handle-Request {
    param([System.Net.HttpListenerContext]$Context)

    $request = $Context.Request
    $response = $Context.Response
    $path = $request.Url.LocalPath

    $script:ServerState.RequestCount++

    # Handle OPTIONS (CORS preflight)
    if ($request.HttpMethod -eq "OPTIONS") {
        Send-Response -Response $response -Content "" -StatusCode 204
        return
    }

    # Only allow GET (read-only per KUHUL class)
    if ($request.HttpMethod -ne "GET") {
        Send-Response -Response $response -Content '{"error": "Method not allowed", "kuhul_class": "api.local.static"}' -StatusCode 405
        return
    }

    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] GET $path" -ForegroundColor Cyan

    # API routes
    if ($ApiEnabled) {
        switch ($path) {
            "/" {
                Send-Response -Response $response -Content (Get-ServerStatus)
                return
            }
            "/status" {
                Send-Response -Response $response -Content (Get-ServerStatus)
                return
            }
            "/health" {
                Send-Response -Response $response -Content (Get-HealthCheck)
                return
            }
            "/api/agents" {
                Send-Response -Response $response -Content (Get-AgentsList)
                return
            }
            "/api/models" {
                Send-Response -Response $response -Content (Get-OllamaModels)
                return
            }
            "/api/ollama/models" {
                Send-Response -Response $response -Content (Get-OllamaModels)
                return
            }
        }
    }

    # Static file serving
    $filePath = Join-Path $Root $path.TrimStart('/')

    # Default to index.html for directories
    if ((Test-Path $filePath) -and (Get-Item $filePath).PSIsContainer) {
        $filePath = Join-Path $filePath "index.html"
    }

    # Serve file
    if (Test-Path $filePath) {
        Send-File -Response $response -FilePath $filePath
    } else {
        Send-Response -Response $response -Content '{"error": "Not Found"}' -StatusCode 404
    }
}

# ═══════════════════════════════════════════════════════════════
# MAIN SERVER LOOP
# ═══════════════════════════════════════════════════════════════

function Start-MX2LMServer {
    # Ensure root directory exists
    if (-not (Test-Path $Root)) {
        New-Item -ItemType Directory -Path $Root -Force | Out-Null
        Write-Host "Created root directory: $Root" -ForegroundColor Yellow
    }

    # Create HTTP listener
    $listener = New-Object System.Net.HttpListener
    $listener.Prefixes.Add("http://localhost:$Port/")
    $listener.Prefixes.Add("http://127.0.0.1:$Port/")

    try {
        $listener.Start()
    } catch {
        Write-Host "Failed to start server: $_" -ForegroundColor Red
        Write-Host "Try running as Administrator or use a different port." -ForegroundColor Yellow
        exit 1
    }

    $script:ServerState.StartTime = Get-Date

    Write-Host ""
    Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor DarkCyan
    Write-Host "  MX2LM Static Server v1.0.0" -ForegroundColor Cyan
    Write-Host "  KUHUL Class: api.local.static" -ForegroundColor DarkGray
    Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor DarkCyan
    Write-Host ""
    Write-Host "  Port:       $Port" -ForegroundColor White
    Write-Host "  Root:       $(Resolve-Path $Root)" -ForegroundColor White
    Write-Host "  API:        $(if ($ApiEnabled) { 'Enabled' } else { 'Disabled' })" -ForegroundColor White
    Write-Host ""
    Write-Host "  Endpoints:" -ForegroundColor Gray
    Write-Host "    http://localhost:$Port/           - Server status" -ForegroundColor DarkGray
    Write-Host "    http://localhost:$Port/health     - Health check" -ForegroundColor DarkGray
    Write-Host "    http://localhost:$Port/api/agents - AI agents list" -ForegroundColor DarkGray
    Write-Host "    http://localhost:$Port/api/models - Ollama models" -ForegroundColor DarkGray
    Write-Host ""
    Write-Host "  Press Ctrl+C to stop" -ForegroundColor Yellow
    Write-Host ""

    try {
        while ($listener.IsListening) {
            $context = $listener.GetContext()
            Handle-Request -Context $context
        }
    } catch {
        if ($_.Exception.Message -notlike "*thread exit*") {
            Write-Host "Server error: $_" -ForegroundColor Red
        }
    } finally {
        $listener.Stop()
        Write-Host "`nServer stopped." -ForegroundColor Yellow
    }
}

# Start the server
Start-MX2LMServer
