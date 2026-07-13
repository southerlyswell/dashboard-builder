I need to check the actual column definitions. Based on the table list:

persons (2776 rows) - likely: id, first_name, last_name, email, phone, branch_id, created_at, status
branches (16 rows) - likely: id, name, location, code
projects (87 rows) - likely: id, name, branch_id, status, start_date, end_date
attendance (152363 rows) - likely: id, person_id, date, check_in, check_out, status, branch_id
staff (30 rows) - likely: id, person_id, role_id, department, hire_date
staff_projects (148 rows) - likely: id, staff_id, project_id, role, start_date
person_projects (7665 rows) - likely: id, person_id, project_id, start_date, end_date, status
project_targets (25 rows) - likely: id, project_id, target_type, target_value
project_schedules (87 rows) - likely: id, project_id, day_of_week, start_time, end_time
non_working_days (73 rows) - likely: id, date, reason, type
roles (5 rows) - likely: id, name, description
login_logs (0 rows) - likely: id, user_id, login_time, ip_address
