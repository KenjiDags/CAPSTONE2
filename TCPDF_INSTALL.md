# TCPDF Installation Instructions

To fix the TCPDF integration in this project, follow these steps:

1. Download TCPDF:
   - Go to https://github.com/tecnickcom/TCPDF/releases
   - Download the latest release ZIP file (e.g., tcpdf_6.6.2.zip)

2. Install TCPDF in your project:
   ```powershell
   # Create tcpdf directory
   mkdir C:\wamp64\www\Inventory-Project\tcpdf

   # Extract the downloaded ZIP
   # Copy all contents from the extracted folder into:
   C:\wamp64\www\Inventory-Project\tcpdf
   ```

3. Verify the installation:
   - Check that this file exists:
     `C:\wamp64\www\Inventory-Project\tcpdf\tcpdf.php`
   - Open http://localhost/Inventory-Project/check_tcpdf.php in your browser
   - It should show "File exists: Yes"

4. Required folder structure:
   ```
   C:\wamp64\www\Inventory-Project\
   ├── tcpdf\
   │   ├── tcpdf.php
   │   ├── config\
   │   ├── fonts\
   │   └── ...
   ├── export_regspi.php
   └── ...
   ```

5. Troubleshooting:
   - If you see "No such file" errors, double-check the folder structure
   - Make sure all files are readable by the web server
   - Clear PHP's opcode cache if changes don't take effect

## Quick Fix Steps

If you're still having issues, run these PowerShell commands:

```powershell
# Navigate to project
cd C:\wamp64\www\Inventory-Project

# Create tcpdf directory if it doesn't exist
if (-not (Test-Path tcpdf)) { New-Item -ItemType Directory -Path tcpdf }

# Download and extract TCPDF
$url = "https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.2.zip"
$output = "$pwd\tcpdf.zip"
Invoke-WebRequest -Uri $url -OutFile $output
Expand-Archive -Path $output -DestinationPath "tcpdf_temp"
Move-Item -Path "tcpdf_temp\TCPDF-6.6.2\*" -Destination "tcpdf"
Remove-Item -Path "tcpdf_temp" -Recurse
Remove-Item -Path $output
```

## Testing the Installation

After installation, visit:
- http://localhost/Inventory-Project/check_tcpdf.php
- http://localhost/Inventory-Project/test_tcpdf.php

Both should work without errors. The test page should generate a sample PDF.