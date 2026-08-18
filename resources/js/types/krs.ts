export type ScheduleSlot = {
    id: number;
    slot_number: number;
    day: string;
    day_label: string;
    starts_at: string;
    ends_at: string;
    raw: string;
};

export type CourseSection = {
    id: number;
    group_code: string;
    time_period: string;
    time_period_label: string;
    schedules: ScheduleSlot[];
};

export type Course = {
    id: number;
    code: string;
    name: string;
    sks: number;
    class_type: string;
    class_type_label: string;
    sections: CourseSection[];
};

export type CourseOffering = {
    id: number;
    title: string;
    source_filename: string;
    imported_at: string;
    courses: Course[];
};

export type PlanItem = {
    id: number;
    course_section_id: number;
    course: {
        id: number;
        code: string;
        name: string;
        sks: number;
    };
    section: {
        id: number;
        group_code: string;
        time_period: string;
        schedules: Array<{
            day: string;
            day_label: string;
            starts_at: string;
            ends_at: string;
        }>;
    };
};

export type KrsPlan = {
    id: number;
    name: string;
    status: string;
    total_sks: number;
    selected_section_ids: number[];
    selected_course_ids: number[];
    unavailable_section_ids: number[];
    course_count: number;
    has_conflicts: boolean;
    conflict_section_ids: number[];
    conflicts: Array<{
        section_a_id: number;
        section_b_id: number;
        day: string;
        starts_at: string;
        ends_at: string;
    }>;
    items: PlanItem[];
};

export type GridConfig = {
    days: Array<{ value: string; label: string }>;
    start_hour: string;
    end_hour: string;
    slot_minutes: number;
};

export type PlanSummary = {
    id: number;
    name: string;
    items_count: number;
};

export type OfferingListItem = {
    id: number;
    title: string;
    source_filename: string;
    imported_at: string;
    courses_count: number;
    plans: PlanSummary[];
};
