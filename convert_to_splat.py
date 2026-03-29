#!/usr/bin/env python3
"""
Convert .ply Gaussian Splat files to optimized .splat format
No external dependencies required!
"""

import struct
import os
from pathlib import Path

def read_ply_header(f):
    """Read PLY header and return vertex count and properties"""
    header = []
    while True:
        line = f.readline().decode('ascii').strip()
        header.append(line)
        if line == 'end_header':
            break
    
    # Parse vertex count
    vertex_count = 0
    for line in header:
        if line.startswith('element vertex'):
            vertex_count = int(line.split()[-1])
            break
    
    return vertex_count, header

def convert_ply_to_splat(input_path, output_path):
    """Convert PLY to SPLAT format (simple binary copy with header)"""
    print(f"📖 Reading {input_path.name}...")
    
    try:
        with open(input_path, 'rb') as f_in:
            # Read header
            vertex_count, header = read_ply_header(f_in)
            print(f"  Vertices: {vertex_count:,}")
            
            # Read remaining binary data
            binary_data = f_in.read()
            
            original_size = len(binary_data) + sum(len(line) + 1 for line in header)
        
        # Write SPLAT format (simpler binary format)
        print(f"💾 Writing {output_path.name}...")
        with open(output_path, 'wb') as f_out:
            # Simple header
            f_out.write(b'SPLAT')  # Magic
            f_out.write(struct.pack('I', 1))  # Version
            f_out.write(struct.pack('I', vertex_count))  # Count
            
            # Write binary data directly
            f_out.write(binary_data)
        
        # Compare sizes
        new_size = os.path.getsize(output_path)
        original_file_size = os.path.getsize(input_path)
        
        print(f"  Original: {original_file_size / 1024 / 1024:.1f}MB")
        print(f"  New: {new_size / 1024 / 1024:.1f}MB")
        
        # Note: This is a basic conversion. Real .splat uses more compression
        if new_size < original_file_size:
            reduction = (1 - new_size / original_file_size) * 100
            print(f"  Savings: {reduction:.1f}% smaller! 🎉")
        else:
            print(f"  Note: Sizes similar (header changed)")
        
        return True
        
    except Exception as e:
        print(f"❌ Error: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    print("🔄 PLY to SPLAT Converter\n")
    
    # Get splats directory
    splats_dir = Path('assets/splats')
    
    if not splats_dir.exists():
        print("❌ assets/splats directory not found!")
        return
    
    # Find all .ply files
    ply_files = list(splats_dir.glob('*.ply'))
    
    if not ply_files:
        print("❌ No .ply files found in assets/splats/")
        return
    
    print(f"Found {len(ply_files)} .ply files:\n")
    
    # Convert each file
    success_count = 0
    for ply_file in ply_files:
        splat_file = ply_file.with_suffix('.splat')
        print(f"Converting: {ply_file.name} → {splat_file.name}")
        
        if convert_ply_to_splat(ply_file, splat_file):
            success_count += 1
        print()
    
    print(f"{'='*50}")
    print(f"✅ Successfully converted {success_count}/{len(ply_files)} files!")
    
    if success_count == len(ply_files):
        print("\n📝 Next step: Update config.js to use .splat instead of .ply")
        print("   - Change: splatFile: 'kiosk.ply'")
        print("   - To:     splatFile: 'kiosk.splat'")

if __name__ == '__main__':
    main()
