from pathlib import Path
from subprocess import run, PIPE

mysql = Path(r'C:/xampp/mysql/bin/mysql.exe')
schema = Path(r'C:/xampp/htdocs/inspectra/database/inspectra_db_complete_schema.sql')

print('Importing updated schema...')
cmd_import = [str(mysql), '-u', 'root', 'inspectra_db']
with schema.open('rb') as f:
    proc = run(cmd_import, stdin=f, stdout=PIPE, stderr=PIPE)

print('import rc:', proc.returncode)
if proc.returncode != 0:
    print('stderr:\n', proc.stderr.decode('utf-8', errors='replace'))
    raise SystemExit('Schema import failed')

# Verify table count
cmd_count = [str(mysql), '-u', 'root', '-N', '-e', "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='inspectra_db';"]
proc2 = run(cmd_count, stdout=PIPE, stderr=PIPE)
count = proc2.stdout.decode('utf-8', errors='replace').strip()
print('total tables:', count)

# List tables
cmd_list = [str(mysql), '-u', 'root', '-N', '-e', "SELECT table_name FROM information_schema.tables WHERE table_schema='inspectra_db' ORDER BY table_name;"]
proc3 = run(cmd_list, stdout=PIPE, stderr=PIPE)
tables = proc3.stdout.decode('utf-8', errors='replace').strip().split('\n')
print('tables:', ', '.join(tables))

# Check for disputes table specifically
if 'disputes' in tables and 'dispute_messages' in tables:
    print('\n✓ disputes table found')
    print('✓ dispute_messages table found')
else:
    print('\n✗ disputes table missing' if 'disputes' not in tables else '')
    print('✗ dispute_messages table missing' if 'dispute_messages' not in tables else '')
