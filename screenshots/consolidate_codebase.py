#!/usr/bin/env python3

import os
import xml.etree.ElementTree as ET
from xml.dom import minidom

def prettify_xml(elem):
    """Return a pretty-printed XML string for the Element."""
    rough_string = ET.tostring(elem, encoding='unicode')
    reparsed = minidom.parseString(rough_string)
    return reparsed.toprettyxml(indent="  ")

def escape_xml_content(content):
    """Escape special XML characters in content."""
    return content.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace('"', '&quot;').replace("'", '&apos;')

def consolidate_files():
    current_dir = os.path.dirname(os.path.abspath(__file__))
    parent_dir = os.path.dirname(current_dir)
    
    # Create root XML element
    root = ET.Element('codebase')  
    
    # Define code file extensions to include
    code_extensions = {
        '.php', '.js', '.css', '.sql', '.json', '.d.ts', '.tpl', '.sh', '.md'
    }
    
    # Define specific markdown files to include (beyond the game design and API docs)
    specific_md_files = {
        'README.md', 'DEPLOYMENT_GUIDE.md', 'IMPLEMENTATION_STAGES.md', 
        'implementation_progress.md', 'game_implementation_plan.md'
    }
    
    # Track processed files for summary
    processed_files = []
    skipped_files = []
    
    # Walk through all directories starting from parent
    for dirpath, dirnames, filenames in os.walk(parent_dir):
        # Skip the current directory
        if os.path.abspath(dirpath) == current_dir:
            dirnames[:] = []  # Don't recurse into subdirectories
            continue
        
        # Process files
        for filename in filenames:
            filepath = os.path.join(dirpath, filename)
            relative_path = os.path.relpath(filepath, parent_dir)
            
            # Check if this is a code file or specific markdown file
            file_ext = os.path.splitext(filename)[1].lower()
            is_code_file = file_ext in code_extensions
            is_specific_md = filename in specific_md_files
            is_game_design = filename == 'game_design.md'
            is_in_api_docs = 'api_docs' in relative_path
            
            # Include if it's a code file, or specific markdown, or game design, or in API docs
            should_include = (is_code_file and file_ext != '.md') or is_specific_md or is_game_design or is_in_api_docs
            
            if should_include:
                try:
                    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                        content = f.read()
                    
                    # Create file element
                    file_elem = ET.SubElement(root, 'file')
                    file_elem.set('path', relative_path)
                    file_elem.set('type', file_ext[1:] if file_ext else 'no_extension')
                    
                    # Add content as text (ET handles escaping)
                    file_elem.text = content
                    
                    processed_files.append(relative_path)
                    print(f"Added: {relative_path}")
                    
                except Exception as e:
                    print(f"Error reading {filepath}: {e}")
                    skipped_files.append(f"{relative_path} (error: {e})")
            else:
                if file_ext == '.md':
                    skipped_files.append(f"{relative_path} (markdown file not in specific list)")
                else:
                    skipped_files.append(f"{relative_path} (not a code file)")
    
    # Write to XML file
    output_file = os.path.join(current_dir, 'consolidated_codebase.xml')
    
    # Pretty print the XML
    xml_string = prettify_xml(root)
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(xml_string)
    
    print(f"\nConsolidation complete! Output saved to: {output_file}")
    print(f"Total files processed: {len(processed_files)}")
    print(f"Total files skipped: {len(skipped_files)}")
    
    # Print summary
    print(f"\nProcessed files:")
    for file_path in processed_files:
        print(f"  ✓ {file_path}")
    
    print(f"\nSkipped files:")
    for file_path in skipped_files:
        print(f"  ✗ {file_path}")

if __name__ == "__main__":
    consolidate_files()