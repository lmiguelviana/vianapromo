$python  = "C:\Users\Miguel\AppData\Local\Programs\Python\Python314\python.exe"
$script  = "C:\xampp\htdocs\viana\bot\main.py"
$botDir  = "C:\xampp\htdocs\viana\bot"

$action   = New-ScheduledTaskAction -Execute $python -Argument """$script""" -WorkingDirectory $botDir
$trigger  = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Hours 6) -Once -At "23:00"
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Hours 1) -MultipleInstances IgnoreNew -StartWhenAvailable

Register-ScheduledTask -TaskName "VianaPromoBot" -Action $action -Trigger $trigger -Settings $settings -RunLevel Highest -Force

Write-Host "OK - Bot agendado para rodar a cada 6 horas!" -ForegroundColor Green
