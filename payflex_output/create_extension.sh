#!/bin/bash

# We need to create a zip file containing the extension files.
# These files are the ones in the filelist.txt file, we also need
# to match the directory structure in the zip file to the one in the
# filelist.txt file.

# Plugin directory is one directory up
plugindir="../"
currentdir=$(pwd)

# Get the file list into an array
filelist=($(<filelist.txt))

cd $plugindir

# The zip file must contain the files in a upload directory
mkdir -p $currentdir/upload

# Loop through the file list, copying the files to the upload directory
for file in "${filelist[@]}"
do
    # Create the directory structure in the zip file
    if [ -d $file ]; then
        mkdir -p $currentdir/upload/$file
    else
        mkdir -p $currentdir/upload/$(dirname $file)
    fi
    # Copy the file to the upload directory
    cp $file $currentdir/upload/$file
done

# Create the zip file
cd $currentdir
zip -r payflex.ocmod.zip upload

# Clean up
rm -rf upload