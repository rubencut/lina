export type UserRole =
  | "super_admin"
  | "staff_teacher_supervisor"
  | "student_employee_participant";

export type User = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  status: string;
};

export type LoginResponse = {
  user: User;
  token: string;
  expires_at: string;
};

export type VerifyCodeResponse = {
  message: string;
  user: User;
};

export type AttendanceRecord = {
  id: number;
  user_id: number;
  classroom_id: number | null;
  date: string;
  time_in: string | null;
  status: "Present" | "Absent" | "Late" | "Excused";
  user?: User;
  classroom?: {
    id: number;
    name: string;
  };
};

export type AttendancePage = {
  data: AttendanceRecord[];
  current_page: number;
  per_page: number;
  total: number;
};

export type MarkQrResponse = {
  message: string;
  already_recorded: boolean;
  attendance: AttendanceRecord;
};
