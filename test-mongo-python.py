#!/usr/bin/env python3
"""
MongoDB Connection Test (Python version)
This script tests connection to MongoDB Atlas using a different language/driver.
"""
import os
import sys
import socket
import dns.resolver
import pymongo
from pymongo.errors import ConnectionFailure

print("MongoDB Python Connection Test")
print("=============================\n")

# Get MongoDB URI from environment variable or .env file
mongo_uri = None

# Try to read from .env file if exists
if os.path.exists('.env'):
    print("Loading from .env file...")
    with open('.env', 'r') as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#'):
                key, value = line.split('=', 1)
                if key == 'MONGO_URI':
                    mongo_uri = value
                    print("Found MONGO_URI in .env")
                elif key == 'MONGO_PASSWORD' and '<db_password>' in mongo_uri:
                    mongo_uri = mongo_uri.replace('<db_password>', value)
                    print("Replaced password placeholder")

if not mongo_uri:
    mongo_uri = os.environ.get('MONGO_URI')
    if not mongo_uri:
        print("ERROR: No MongoDB connection string found!")
        sys.exit(1)

# Mask password for display
masked_uri = mongo_uri
if '@' in mongo_uri:
    parts = mongo_uri.split('@')
    prefix = parts[0]
    if ':' in prefix:
        user_pass = prefix.split(':')
        masked_uri = f"{user_pass[0]}:***@{'@'.join(parts[1:])}"

print(f"Using connection string: {masked_uri}")

# Extract hostname
if 'mongodb+srv://' in mongo_uri:
    hostname = mongo_uri.split('@')[1].split('/')[0]
else:
    hostname = mongo_uri.split('@')[1].split('/')[0].split(':')[0]

print(f"\nHostname: {hostname}")

# Perform DNS checks
print("\n--- DNS Tests ---")
try:
    print(f"Resolving {hostname}...")
    ip_address = socket.gethostbyname(hostname)
    print(f"Resolved to {ip_address}")
except socket.gaierror as e:
    print(f"DNS resolution failed: {str(e)}")

# Try with dnspython if SRV record
if 'mongodb+srv' in mongo_uri:
    print("\nTrying SRV lookup...")
    try:
        answers = dns.resolver.resolve('_mongodb._tcp.' + hostname, 'SRV')
        for rdata in answers:
            print(f"SRV record: {rdata.target} (Priority: {rdata.priority}, Weight: {rdata.weight}, Port: {rdata.port})")
            try {
                ip = socket.gethostbyname(str(rdata.target))
                print(f" - Resolves to: {ip}")
            } catch (socket.gaierror) {
                print(f" - Could not resolve IP")
            }
        }
    } catch (Exception as e) {
        print(f"SRV lookup failed: {str(e)}")
    }

# Test connectivity
print("\n--- Connection Tests ---")
connection_options = {
    'serverSelectionTimeoutMS': 10000,
    'connectTimeoutMS': 20000,
    'tlsAllowInvalidCertificates': True,  # Only for testing
    'tlsAllowInvalidHostnames': True,     # Only for testing
    'retryWrites': True
}

print("Connecting to MongoDB...")
try:
    client = pymongo.MongoClient(mongo_uri, **connection_options)
    print("Client instance created, testing with ping...")
    
    # Force connection
    client.admin.command('ping')
    print("✅ Connection successful!")
    
    # List databases
    print("\nDatabases available:")
    for db_info in client.list_databases():
        print(f" - {db_info['name']}")
        
except ConnectionFailure as e:
    print(f"❌ Connection failed: {str(e)}")
    print("\nError analysis:")
    if "timed out" in str(e):
        print("- Connection timed out: Network connectivity issue or firewall blocking")
    elif "SSL" in str(e) or "certificate" in str(e):
        print("- SSL/TLS issue: Certificate validation problem")
    elif "auth" in str(e).lower():
        print("- Authentication failed: Check username and password")
except Exception as e:
    print(f"❌ Error: {str(e)}")

print("\nTest complete")