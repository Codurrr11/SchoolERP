<?php
require_once dirname(__DIR__) . '/config/db.php';

try {
    $student_id = 18; // Kunal Verma
    $school_id = 1;
    
    // Clear old attendance records for student 18 to avoid unique key conflicts
    $pdo->prepare("DELETE FROM student_attendance WHERE student_id = :student_id")->execute([':student_id' => $student_id]);
    
    $records = [
        // Date, Status, Check In, Check Out, Leave Type, Leave Reason
        ['2026-06-01', 'present', '08:30:00', '14:30:00', null, null],
        ['2026-06-02', 'present', '08:25:00', '14:35:00', null, null],
        ['2026-06-03', 'present', '08:28:00', '14:30:00', null, null],
        ['2026-06-04', 'present', '08:31:00', '14:30:00', null, null],
        ['2026-06-05', 'present', '08:29:00', '14:30:00', null, null],
        ['2026-06-08', 'late', '09:15:00', '14:30:00', null, null],
        ['2026-06-09', 'absent', null, null, null, null],
        ['2026-06-10', 'absent', null, null, null, null],
        ['2026-06-11', 'absent', null, null, null, null],
        ['2026-06-12', 'absent', null, null, null, null],
        ['2026-06-13', 'absent', null, null, null, null],
        ['2026-06-14', 'absent', null, null, null, null], // Sunday absent as in screenshot
        ['2026-06-15', 'leave', null, null, 'Sick Leave', 'Fever and cold'],
        ['2026-06-16', 'absent', null, null, null, null],
        ['2026-06-17', 'absent', null, null, null, null],
        ['2026-06-18', 'absent', null, null, null, null],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO student_attendance (school_id, student_id, date, status, check_in, check_out, leave_type, leave_reason)
        VALUES (:school_id, :student_id, :date, :status, :check_in, :check_out, :leave_type, :leave_reason)
    ");
    
    foreach ($records as $r) {
        $stmt->execute([
            ':school_id' => $school_id,
            ':student_id' => $student_id,
            ':date' => $r[0],
            ':status' => $r[1],
            ':check_in' => $r[2],
            ':check_out' => $r[3],
            ':leave_type' => $r[4],
            ':leave_reason' => $r[5]
        ]);
    }
    
    echo "Successfully seeded June 2026 attendance for student 18.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
