# Sefrengo 2.0.1 - PHP 8.x, modern password hashing
# passwords are stored with password_hash() (Argon2id/bcrypt, up to 255 chars).
# existing md5 hashes keep working and are upgraded on the next successful login.
ALTER TABLE cms_users MODIFY password varchar(255) NOT NULL default '';
UPDATE cms_values SET value = '02.00.01' WHERE group_name = 'cfg' AND key1 = 'version';
