<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'class_models';

    protected $fillable = [
        'name',
        'course',
        'class_code',
        'class_name',  // Added for N8N API compatibility
        'section',
        'year',
        'subject',
        'description',
        'teacher_id',
        'schedule',
        'schedule_time',
        'schedule_days',
        'is_active',
        'room',
        'academic_year',
        'semester'
    ];

    protected $casts = [
        'schedule_days' => 'array',
        'is_active' => 'boolean',
        'schedule_time' => 'datetime:H:i',
    ];

    // Ensure boolean values are properly handled
    public function setIsActiveAttribute($value)
    {
        $this->attributes['is_active'] = (bool) $value;
    }

    // Relationships

    /**
     * Get the teacher user (for N8N API)
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the teacher record
     */
    public function teacherRecord()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'user_id');
    }

    /**
     * Direct relationship (students with class_id pointing to this class)
     */
    public function directStudents()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * Many-to-many relationship (students enrolled via pivot table)
     * This is the primary method for N8N API
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_student', 'class_model_id', 'student_id')
                    ->withPivot(['is_active', 'enrolled_at', 'dropped_at', 'status'])
                    ->withTimestamps();
    }

    /**
     * Alias for students() - for backward compatibility
     */
    public function enrolledStudents()
    {
        return $this->students();
    }

    /**
     * Get only active enrolled students
     */
    public function activeStudents()
    {
        return $this->students()->wherePivot('is_active', true);
    }

    /**
     * Get students by matching course and section
     */
    public function getStudentsBySection()
    {
        return Student::where('course', $this->course)
            ->where('section', $this->section)
            ->get();
    }

    /**
     * Get students count by matching course and section
     */
    public function getStudentsCountBySection()
    {
        return Student::where('course', $this->course)
            ->where('section', $this->section)
            ->count();
    }

    /**
     * Get attendance sessions for this class
     */
    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class, 'class_id');
    }

    /**
     * Get class files
     */
    public function classFiles()
    {
        return $this->hasMany(ClassFile::class, 'class_id');
    }

    // Scopes
    
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeActive($query)
    {
        return $query->whereRaw('COALESCE(is_active, true) = true');
    }

    // Accessors
    
    public function getStudentCountAttribute()
    {
        // Count from many-to-many relationship
        return $this->students()->count();
    }

    public function getLastSessionAttribute()
    {
        return $this->attendanceSessions()
                    ->latest('session_date')
                    ->first();
    }

    /**
     * Get class name (accessor for N8N API compatibility)
     * Falls back to 'name' if 'class_name' doesn't exist
     */
    public function getClassNameAttribute()
    {
        return $this->attributes['class_name'] ?? $this->attributes['name'] ?? 'Unnamed Class';
    }
}
