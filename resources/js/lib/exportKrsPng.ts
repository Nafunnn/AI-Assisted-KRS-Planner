import {
    courseColorHex,
    formatGeneratedStamp,
    timeToMinutes,
} from '@/lib/krs';
import type { CourseOffering, GridConfig, KrsPlan } from '@/types/krs';

type ExportOptions = {
    offering: CourseOffering;
    plan: KrsPlan;
    gridConfig: GridConfig;
    appName?: string;
};

function wrapText(
    ctx: CanvasRenderingContext2D,
    text: string,
    maxWidth: number,
    maxLines: number,
): string[] {
    const words = text.split(' ');
    const lines: string[] = [];
    let current = '';

    for (const word of words) {
        const next = current ? `${current} ${word}` : word;

        if (ctx.measureText(next).width <= maxWidth) {
            current = next;
            continue;
        }

        if (current) {
            lines.push(current);
        }

        current = word;

        if (lines.length === maxLines - 1) {
            break;
        }
    }

    if (lines.length < maxLines && current) {
        let last = current;

        while (ctx.measureText(last).width > maxWidth && last.length > 1) {
            last = last.slice(0, -1);
        }

        if (ctx.measureText(current).width > maxWidth) {
            last = `${last.slice(0, Math.max(1, last.length - 1))}…`;
        }

        lines.push(last);
    }

    return lines.slice(0, maxLines);
}

export function downloadKrsPlanPng({
    offering,
    plan,
    gridConfig,
    appName = 'KRS Planner',
}: ExportOptions): void {
    const stamp = formatGeneratedStamp(new Date(), appName);
    const scale = 2;
    const width = 1400;
    const headerHeight = 118;
    const footerHeight = 64;
    const timeColWidth = 72;
    const rowHeight = 26;
    const gridStart = timeToMinutes(gridConfig.start_hour);
    const gridEnd = timeToMinutes(gridConfig.end_hour);
    const totalMinutes = Math.max(gridEnd - gridStart, 1);
    const slotCount = Math.ceil(totalMinutes / gridConfig.slot_minutes);
    const gridHeight = slotCount * rowHeight;
    const height = headerHeight + gridHeight + footerHeight;
    const dayWidth = (width - timeColWidth) / gridConfig.days.length;

    const canvas = document.createElement('canvas');
    canvas.width = width * scale;
    canvas.height = height * scale;

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('Canvas 2D tidak tersedia di browser ini.');
    }

    ctx.scale(scale, scale);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);

    ctx.fillStyle = '#111827';
    ctx.font = 'bold 28px Arial, sans-serif';
    ctx.fillText(offering.title, 32, 42);

    ctx.font = '16px Arial, sans-serif';
    ctx.fillStyle = '#4b5563';
    ctx.fillText(
        `${plan.course_count} mata kuliah  •  ${plan.total_sks} SKS  •  ${plan.has_conflicts ? 'Ada bentrok jadwal' : 'Tidak ada bentrok'}`,
        32,
        72,
    );

    ctx.save();
    ctx.translate(40, 220);
    ctx.rotate((-22 * Math.PI) / 180);
    ctx.font = 'bold 64px Arial, sans-serif';
    ctx.fillStyle = 'rgba(17, 24, 39, 0.05)';
    ctx.fillText(appName, 0, 0);
    ctx.restore();

    const gridTop = headerHeight;
    const gridLeft = 0;

    ctx.fillStyle = '#f3f4f6';
    ctx.fillRect(gridLeft, gridTop, width, 36);

    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 1;
    ctx.font = 'bold 13px Arial, sans-serif';
    ctx.fillStyle = '#374151';
    ctx.fillText('Jam', gridLeft + 16, gridTop + 24);

    gridConfig.days.forEach((day, index) => {
        const x = timeColWidth + index * dayWidth;
        ctx.fillText(day.label, x + dayWidth / 2 - 14, gridTop + 24);
        ctx.beginPath();
        ctx.moveTo(x, gridTop);
        ctx.lineTo(x, gridTop + 36 + gridHeight);
        ctx.stroke();
    });

    const bodyTop = gridTop + 36;

    ctx.font = '11px Arial, sans-serif';
    ctx.fillStyle = '#6b7280';

    for (let index = 0; index < slotCount; index++) {
        const minutes = gridStart + index * gridConfig.slot_minutes;
        const hours = Math.floor(minutes / 60)
            .toString()
            .padStart(2, '0');
        const mins = (minutes % 60).toString().padStart(2, '0');
        const y = bodyTop + index * rowHeight;

        ctx.fillStyle = index % 2 === 0 ? '#ffffff' : '#f9fafb';
        ctx.fillRect(timeColWidth, y, width - timeColWidth, rowHeight);

        ctx.fillStyle = '#6b7280';
        ctx.fillText(`${hours}:${mins}`, gridLeft + 12, y + 18);

        ctx.strokeStyle = '#e5e7eb';
        ctx.beginPath();
        ctx.moveTo(gridLeft, y);
        ctx.lineTo(width, y);
        ctx.stroke();
    }

    ctx.strokeStyle = '#d1d5db';
    ctx.strokeRect(gridLeft, gridTop, width, 36 + gridHeight);

    plan.items.forEach((item) => {
        item.section.schedules.forEach((schedule) => {
            const dayIndex = gridConfig.days.findIndex(
                (day) => day.value === schedule.day,
            );

            if (dayIndex < 0) {
                return;
            }

            const start = timeToMinutes(schedule.starts_at);
            const end = timeToMinutes(schedule.ends_at);
            const top =
                bodyTop + ((start - gridStart) / totalMinutes) * gridHeight;
            const blockHeight = Math.max(
                ((end - start) / totalMinutes) * gridHeight,
                28,
            );
            const x = timeColWidth + dayIndex * dayWidth + 6;
            const w = dayWidth - 12;

            ctx.save();
            ctx.fillStyle = courseColorHex(item.course.id);
            ctx.beginPath();

            if (typeof ctx.roundRect === 'function') {
                ctx.roundRect(x, top, w, blockHeight, 6);
            } else {
                ctx.rect(x, top, w, blockHeight);
            }

            ctx.fill();
            ctx.clip();

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 12px Arial, sans-serif';
            ctx.fillText(item.course.code, x + 8, top + 16);

            ctx.font = '11px Arial, sans-serif';
            const nameLines = wrapText(ctx, item.course.name, w - 16, 2);
            nameLines.forEach((line, lineIndex) => {
                ctx.fillText(line, x + 8, top + 32 + lineIndex * 14);
            });

            ctx.fillText(
                `${schedule.starts_at} - ${schedule.ends_at}`,
                x + 8,
                top + 32 + nameLines.length * 14 + 4,
            );
            ctx.fillText(
                item.section.group_code,
                x + 8,
                top + 32 + nameLines.length * 14 + 18,
            );
            ctx.restore();
        });
    });

    const footerTop = headerHeight + 36 + gridHeight;
    ctx.fillStyle = '#111827';
    ctx.fillRect(0, footerTop, width, footerHeight);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 14px Arial, sans-serif';
    ctx.fillText(stamp, 32, footerTop + 28);
    ctx.font = '12px Arial, sans-serif';
    ctx.fillStyle = '#d1d5db';
    ctx.fillText(
        'Jadwal ini dibuat otomatis oleh sistem KRS Planner',
        32,
        footerTop + 48,
    );

    const link = document.createElement('a');
    link.download = `krs-${plan.id}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}
