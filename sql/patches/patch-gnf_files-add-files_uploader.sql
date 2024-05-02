ALTER TABLE /*_*/gnf_files
  ADD COLUMN files_uploader INT UNSIGNED DEFAULT NULL AFTER files_name;
