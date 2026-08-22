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
    deprecated_at?: string | null;
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
    term?: string;
    source_filename: string;
    catalog_version?: number;
    imported_at: string;
    published_at?: string | null;
    courses: Course[];
};

export type PlanItem = {
    id: number;
    course_section_id: number;
    status?: string;
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
        deprecated_at?: string | null;
        schedules: Array<{
            day: string;
            day_label: string;
            starts_at: string;
            ends_at: string;
        }>;
    };
};

export type SectionConflict = {
    section_id: number;
    course_code: string;
    course_name: string;
    group_code: string;
    day: string;
    day_label: string;
    starts_at: string;
    ends_at: string;
};

export type UnavailableSection = {
    section_id: number;
    conflicts_with: SectionConflict[];
};

export type KrsPlan = {
    id: number;
    name: string;
    status: string;
    is_shared_with_friends?: boolean;
    total_sks: number;
    selected_section_ids: number[];
    selected_course_ids: number[];
    unavailable_section_ids: number[];
    unavailable_sections: UnavailableSection[];
    course_count: number;
    has_conflicts: boolean;
    has_stale_items?: boolean;
    stale_items_count?: number;
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
    term?: string;
    source_filename: string;
    catalog_version?: number;
    imported_at: string;
    courses_count: number;
    plans: PlanSummary[];
};

export type CompareScheduleSlot = {
    day: string;
    day_label: string;
    starts_at: string;
    ends_at: string;
};

export type CompareSection = {
    section_id: number;
    course_id: number;
    code: string;
    name: string;
    sks: number;
    group_code: string;
    time_period: string;
    time_period_label: string;
    schedules: CompareScheduleSlot[];
};

export type ComparePlanSummary = {
    id: number;
    name: string;
    offering_id: number;
    total_sks: number;
    course_count: number;
    owner: {
        id: number;
        name: string;
    };
};

export type CompareTimeOverlap = {
    day: string;
    day_label: string;
    overlap_starts_at: string;
    overlap_ends_at: string;
    overlap_minutes: number;
    section_a: {
        section_id: number;
        course_id: number;
        code: string;
        name: string;
        group_code: string;
        starts_at: string;
        ends_at: string;
    };
    section_b: {
        section_id: number;
        course_id: number;
        code: string;
        name: string;
        group_code: string;
        starts_at: string;
        ends_at: string;
    };
};

export type CompareCalendarBlock = {
    plan: 'a' | 'b' | 'both';
    course_id: number;
    section_id: number;
    code: string;
    name: string;
    group_code: string;
    day: string;
    day_label: string;
    starts_at: string;
    ends_at: string;
    has_time_overlap: boolean;
};

export type CompareStats = {
    same_count: number;
    only_a_count: number;
    only_b_count: number;
    time_overlap_count: number;
    sks_a: number;
    sks_b: number;
    same_sks: number;
};

export type MyPlanOption = {
    id: number;
    name: string;
    offering_id: number;
    offering_title: string;
    items_count: number;
};
