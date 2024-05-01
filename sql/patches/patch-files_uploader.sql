ALTER TABLE /*_*/gnf_files
  ADD COLUMN files_uploader INT UNSIGNED NOT NULL AFTER files_name;
