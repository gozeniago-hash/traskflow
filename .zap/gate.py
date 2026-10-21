"""Fail the DAST job on High-risk ZAP alerts (riskcode 3). Others are reported, not blocking."""
import json, sys

report = json.load(open(sys.argv[1]))
high = [(a.get('pluginid'), a.get('alert'), a.get('count')) for site in report.get('site', [])
        for a in site.get('alerts', []) if str(a.get('riskcode')) == '3']
for pid, name, count in high:
    print(f'HIGH  {pid}  {name}  ({count} instances)')
print(f'{len(high)} high-risk alert type(s)')
sys.exit(1 if high else 0)
