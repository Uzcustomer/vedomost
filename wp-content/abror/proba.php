<?php
/**
 * proba.php - Joriy baholari o'rtachasi hisobot (Yangi dizayn)
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__, 2) . '/wp-config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die('DB connection error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function db_all(PDO $pdo, string $sql): array {
    $st = $pdo->query($sql);
    return $st->fetchAll();
}

$educationTypes = db_all($pdo, "SELECT id, hemis_id, name FROM hemis_education_types ORDER BY hemis_id");
$years = db_all($pdo, "SELECT id, hemis_id, name, `current` FROM hemis_education_years ORDER BY name DESC");
$departments = db_all($pdo, "SELECT id, hemis_id, name FROM hemis_departments ORDER BY name");
$specialties = db_all($pdo, "SELECT id, hemis_id, department_id, name FROM hemis_specialties ORDER BY name");
$curriculums = db_all($pdo, "SELECT c.id, c.hemis_id, c.education_type_id, c.education_year_id, c.department_id, c.specialty_id, c.name FROM hemis_curriculums c ORDER BY c.name");
$levels = db_all($pdo, "SELECT id, hemis_id, name FROM hemis_levels ORDER BY name");
$semesters = db_all($pdo, "SELECT id, hemis_id, name FROM hemis_semesters ORDER BY hemis_id");
$currentSemesters = db_all($pdo, "SELECT education_year_id, level_id, semester_id FROM hemis_current_semesters");
$subjects = db_all($pdo, "SELECT s.id, s.hemis_id, s.curriculum_id, s.education_type_id, s.education_year_id, s.department_id, s.specialty_id, s.level_id, s.semester_id, s.name, s.short_name, COALESCE(s.active, 1) as active, COALESCE(s.at_semester, 1) as at_semester FROM hemis_subjects s ORDER BY s.name");
$groups = db_all($pdo, "SELECT id, hemis_id, education_type_id, education_year_id, department_id, specialty_id, level_id, semester_id, name FROM hemis_groups ORDER BY name");
$studentSubjects = db_all($pdo, "SELECT * FROM hemis_student_subjects");
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joriy baholari o'rtachasi hisobot</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        
        .container { 
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 15px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .header p {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 25px 40px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px 20px;
            max-width: 100%;
            margin: 0 auto 25px auto;
            padding-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .filter-row {
            display: flex;
            flex-direction: column;
        }
        
        .filter-row.full-width {
            grid-column: span 2;
        }
        
        .filter-row label {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .dropdown {
            position: relative;
            width: 100%;
            font-size: 12px;
        }
        
        .dropdown-display {
            border: 2px solid #e0e0e0;
            background: #fff;
            padding: 8px 30px 8px 10px;
            cursor: pointer;
            border-radius: 6px;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
        }
        
        .dropdown-display:hover {
            border-color: #1976d2;
        }
        
        .dropdown-display span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 12px;
        }
        
        .dropdown-display::after {
            content: "▼";
            font-size: 9px;
            margin-left: 8px;
            color: #666;
        }
        
        .dropdown.open .dropdown-display {
            border-color: #1976d2;
        }
        
        .dropdown.open .dropdown-display::after {
            content: "▲";
        }
        
        .dropdown-panel {
            position: fixed;
            background: #fff;
            border: 2px solid #1976d2;
            border-radius: 6px;
            margin-top: 5px;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            max-width: 400px;
            max-height: 280px;
            overflow: hidden;
        }
        
        .dropdown.open .dropdown-panel {
            display: block;
        }
        
        .dropdown-search {
            width: calc(100% - 12px);
            margin: 6px 6px;
            padding: 6px 8px;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .dropdown-options {
            max-height: 220px;
            overflow-y: auto;
            padding: 3px 0 15px 0;
        }
        
        .dropdown-option {
            padding: 6px 10px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
        }
        .dropdown-option:last-child {
         margin-bottom: 10px;       
        }                              
        .dropdown-option:hover {
            background: #e3f2fd;
        }
        
        .dropdown-option.disabled {
            color: #999;
            cursor: default;
            background: transparent;
        }
        
        .dropdown-option input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
        }
        
        .dropdown-placeholder {
            color: #999;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .checkbox-label:hover {
            background: #e9ecef;
        }
        
        .checkbox-label input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        
        .checkbox-label span {
            font-size: 13px;
            color: #555;
        }
        
        .date-input {
            width: 100%;
            padding: 10px 12px;
            font-size: 13px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            transition: border-color 0.2s;
        }
        
        .date-input:focus {
            outline: none;
            border-color: #1976d2;
        }
        
        .select-all-btn {
            background: #f0f0f0;
            border: none;
            padding: 5px 10px;
            margin: 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            color: #1976d2;
            transition: background 0.2s;
        }
        
        .select-all-btn:hover {
            background: #e0e0e0;
        }
        
        .filter-row label {
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }
        
        .buttons-container {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 118, 210, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.4);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
            color: white;
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(2, 136, 209, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Scrollbar */
        .dropdown-options::-webkit-scrollbar {
            width: 6px;
        }
        
        .dropdown-options::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .dropdown-options::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .dropdown-options::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📊 Joriy baholari o'rtachasi hisobot</h1>
        <p>Talabalarning joriy nazorat baholari bo'yicha hisobot tuzish tizimi</p>
    </div>
    
    <div class="form-container">
        <div class="filters-grid">
            <!-- 1-qator: Ta'lim turi va O'quv yili -->
            <div class="filter-row">
                <label>Ta'lim turi:</label>
                <div class="dropdown" data-name="education_type_id" id="ddl-education-type"></div>
            </div>
            
            <div class="filter-row">
                <label>O'quv yili:</label>
                <div class="dropdown" data-name="education_year_id" id="ddl-year"></div>
            </div>
            
            <!-- 2-qator: Fakultet va Yo'nalish -->
            <div class="filter-row">
                <label>Fakultet:</label>
                <div class="dropdown" data-name="department_id" id="ddl-department"></div>
            </div>
            
            <div class="filter-row">
                <label>Yo'nalish:</label>
                <div class="dropdown" data-name="specialty_id" id="ddl-specialty"></div>
            </div>
            
            <!-- 3-qator: Kurs va Semestr -->
            <div class="filter-row">
                <label>Kurs:</label>
                <div class="dropdown" data-name="level_id" id="ddl-level"></div>
            </div>
            
            <div class="filter-row">
                <label>Semestr:</label>
                <div class="dropdown" data-name="semester_id" id="ddl-semester"></div>
            </div>
            
            <!-- Fan (2 ustun) -->
            <div class="filter-row full-width">
                <label>Fan:</label>
                <div class="dropdown" data-name="subject_id" id="ddl-subject"></div>
            </div>
            
            <!-- Guruhlar (2 ustun) -->
            <div class="filter-row full-width">
                <label>Guruh(lar):</label>
                <div class="dropdown" data-name="group_ids" data-multiple="1" id="ddl-groups"></div>
            </div>
        </div>
        
        <div class="buttons-container">
            <button type="button" class="btn btn-primary" onclick="updateData()">
                <span>🔄</span> Ma'lumotlarni yangilash
            </button>
            <button type="button" class="btn btn-success" onclick="exportExcel('yn_oldi')">
                <span>📊</span> YN oldi qaydnoma yaratish
            </button>
            <button type="button" class="btn btn-info" onclick="exportExcel('yn')">
                <span>📋</span> YN qaydnoma yaratish
            </button>
        </div>
    </div>
</div>

<script>
const EDUCATION_TYPES = <?= json_encode($educationTypes, JSON_UNESCAPED_UNICODE) ?>;
const YEARS = <?= json_encode($years, JSON_UNESCAPED_UNICODE) ?>;
const DEPARTMENTS = <?= json_encode($departments, JSON_UNESCAPED_UNICODE) ?>;
const SPECS = <?= json_encode($specialties, JSON_UNESCAPED_UNICODE) ?>;
const CURRICULUMS = <?= json_encode($curriculums, JSON_UNESCAPED_UNICODE) ?>;
const LEVELS = <?= json_encode($levels, JSON_UNESCAPED_UNICODE) ?>;
const SEMESTERS = <?= json_encode($semesters, JSON_UNESCAPED_UNICODE) ?>;
const CURRENT_SEMESTERS = <?= json_encode($currentSemesters, JSON_UNESCAPED_UNICODE) ?>;
const SUBJECTS = <?= json_encode($subjects, JSON_UNESCAPED_UNICODE) ?>;
const GROUPS = <?= json_encode($groups, JSON_UNESCAPED_UNICODE) ?>;
const STUDENT_SUBJECTS = <?= json_encode($studentSubjects, JSON_UNESCAPED_UNICODE) ?>;

// Dropdown class
class Dropdown {
    constructor(el, options = []) {
        this.el = el;
        this.name = el.dataset.name;
        this.multiple = el.dataset.multiple === '1';
        this.isOpen = false;
        
        this.render();
        this.setOptions(options);
        this.attachEvents();
    }
    
    render() {
        this.el.innerHTML = `
            <input type="hidden" name="${this.name}" value="">
            <div class="dropdown-display">
                <span class="dropdown-placeholder">Tanlang...</span>
            </div>
            <div class="dropdown-panel">
                ${this.multiple ? '<button type="button" class="select-all-btn">✓ Barchasini tanlash</button>' : ''}
                <input type="text" class="dropdown-search" placeholder="🔍 Qidirish...">
                <div class="dropdown-options"></div>
            </div>
        `;
        
        this.display = this.el.querySelector('.dropdown-display span');
        this.input = this.el.querySelector('input[type="hidden"]');
        this.panel = this.el.querySelector('.dropdown-panel');
        this.search = this.el.querySelector('.dropdown-search');
        this.optionsContainer = this.el.querySelector('.dropdown-options');
        this.selectAllBtn = this.el.querySelector('.select-all-btn');
    }
    
    attachEvents() {
        this.el.querySelector('.dropdown-display').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });
        
        this.search.addEventListener('input', () => this.filterOptions());
        this.search.addEventListener('click', (e) => e.stopPropagation());
        
        if (this.selectAllBtn) {
            this.selectAllBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectAll();
            });
        }
        
        document.addEventListener('click', (e) => {
            if (!this.el.contains(e.target)) {
                this.close();
            }
        });
    }
    
    toggle() {
        this.isOpen ? this.close() : this.open();
    }
    
    open() {
        this.isOpen = true;
        this.el.classList.add('open');
        this.search.value = '';
        this.filterOptions();
        
        // Panel pozitsiyasini hisoblash
        const rect = this.el.querySelector('.dropdown-display').getBoundingClientRect();
        const panelHeight = 280; // Panel max-height
        const spaceBelow = window.innerHeight - rect.bottom - 10; // Pastdagi bo'sh joy
        const spaceAbove = rect.top - 10; // Yuqoridagi bo'sh joy
        
        this.panel.style.left = rect.left + 'px';
        this.panel.style.width = rect.width + 'px';
        
        // BO'SH JOYGA QARAB OCHILISH
        if (spaceBelow >= panelHeight) {
            // Pastda yetarli joy bor - pastga ochiladi
            this.panel.style.top = (rect.bottom + 5) + 'px';
            this.panel.style.bottom = 'auto';
        } else if (spaceAbove >= panelHeight) {
            // Yuqorida yetarli joy bor - yuqoriga ochiladi
            this.panel.style.bottom = (window.innerHeight - rect.top + 5) + 'px';
            this.panel.style.top = 'auto';
        } else if (spaceBelow > spaceAbove) {
            // Pastda ko'proq joy - pastga ochiladi, lekin max-height ga sig'diradi
            this.panel.style.top = (rect.bottom + 5) + 'px';
            this.panel.style.bottom = 'auto';
            this.panel.style.maxHeight = spaceBelow + 'px';
        } else {
            // Yuqorida ko'proq joy - yuqoriga ochiladi, lekin max-height ga sig'diradi
            this.panel.style.bottom = (window.innerHeight - rect.top + 5) + 'px';
            this.panel.style.top = 'auto';
            this.panel.style.maxHeight = spaceAbove + 'px';
        }
        
        setTimeout(() => this.search.focus(), 100);
    }
    
    close() {
        this.isOpen = false;
        this.el.classList.remove('open');
    }
    
    setOptions(options) {
        this.options = options;
        this.renderOptions();
    }
    
    renderOptions() {
        this.optionsContainer.innerHTML = '';
        
        if (this.options.length === 0) {
            this.optionsContainer.innerHTML = '<div class="dropdown-option disabled">Ma\'lumot yo\'q</div>';
            return;
        }
        
        this.options.forEach(opt => {
            const div = document.createElement('div');
            div.className = 'dropdown-option';
            div.dataset.value = opt.value;
            
            if (this.multiple) {
                div.innerHTML = `
                    <input type="checkbox" name="${this.name}[]" value="${opt.value}">
                    <span>${opt.label}</span>
                `;
                div.addEventListener('click', (e) => {
                    if (e.target.tagName !== 'INPUT') {
                        const cb = div.querySelector('input');
                        cb.checked = !cb.checked;
                    }
                    this.updateMultipleDisplay();
                });
            } else {
                div.textContent = opt.label;
                div.addEventListener('click', () => {
                    this.setValue(opt.value, opt.label);
                    this.close();
                    this.onChange(opt.value);
                });
            }
            
            this.optionsContainer.appendChild(div);
        });
    }
    
    filterOptions() {
        const query = this.search.value.toLowerCase();
        const options = this.optionsContainer.querySelectorAll('.dropdown-option');
        
        options.forEach(opt => {
            const text = opt.textContent.toLowerCase();
            opt.style.display = text.includes(query) ? 'flex' : 'none';
        });
    }
    
    setValue(value, label) {
        this.input.value = value;
        this.display.textContent = label;
        this.display.classList.remove('dropdown-placeholder');
    }
    
    selectAll() {
        const checkboxes = this.optionsContainer.querySelectorAll('input[type="checkbox"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        this.updateMultipleDisplay();
    }
    
    updateMultipleDisplay() {
        const checked = this.optionsContainer.querySelectorAll('input[type="checkbox"]:checked');
        
        if (checked.length === 0) {
            this.display.textContent = 'Tanlang...';
            this.display.classList.add('dropdown-placeholder');
        } else {
            // Hammasi tanlanganligini tekshirish
            const total = this.optionsContainer.querySelectorAll('input[type="checkbox"]').length;
            
            if (checked.length === total) {
                this.display.textContent = 'Barchasi tanlangan';
                this.display.classList.remove('dropdown-placeholder');
            } else {
                // Guruh nomlarini nuqtali vergul bilan
                const names = Array.from(checked).map(cb => cb.nextElementSibling.textContent.trim());
                const displayText = names.join('; ');
                
                // Agar juda uzun bo'lsa, qisqartirish
                if (displayText.length > 60) {
                    this.display.textContent = displayText.substring(0, 57) + '...';
                } else {
                    this.display.textContent = displayText;
                }
                this.display.classList.remove('dropdown-placeholder');
            }
        }
    }
    
    onChange(value) {
        // Override this method
    }
}

// Initialize
let eduTypeDD, yearDD, deptDD, specDD, levelDD, semDD, subjDD, groupsDD;
let eduTypeInput, yearInput, deptInput, specInput, levelInput, semInput;

function initFilters() {
    eduTypeDD = new Dropdown(document.getElementById('ddl-education-type'));
    yearDD = new Dropdown(document.getElementById('ddl-year'));
    deptDD = new Dropdown(document.getElementById('ddl-department'));
    specDD = new Dropdown(document.getElementById('ddl-specialty'));
    levelDD = new Dropdown(document.getElementById('ddl-level'));
    semDD = new Dropdown(document.getElementById('ddl-semester'));
    subjDD = new Dropdown(document.getElementById('ddl-subject'));
    groupsDD = new Dropdown(document.getElementById('ddl-groups'));
    
    eduTypeInput = document.querySelector('input[name="education_type_id"]');
    yearInput = document.querySelector('input[name="education_year_id"]');
    deptInput = document.querySelector('input[name="department_id"]');
    specInput = document.querySelector('input[name="specialty_id"]');
    levelInput = document.querySelector('input[name="level_id"]');
    semInput = document.querySelector('input[name="semester_id"]');
    
    eduTypeDD.setOptions(EDUCATION_TYPES.map(t => ({ value: t.id, label: t.name })));
    yearDD.setOptions(YEARS.map(y => ({ value: y.id, label: y.name })));
    levelDD.setOptions(LEVELS.map(l => ({ value: l.id, label: l.name })));
    semDD.setOptions(SEMESTERS.map(s => ({ value: s.id, label: s.name })));
    
    eduTypeDD.onChange = (v) => handleEducationTypeChange(v);
    yearDD.onChange = (v) => handleYearChange(v);
    deptDD.onChange = (v) => handleDepartmentChange(v);
    specDD.onChange = (v) => { handleSpecialtyChange(v); recomputeSubjectsAndGroups(); };
    levelDD.onChange = (v) => {
        autoSelectCurrentSemester(v);
        // Kurs o'zgarganda ham fanlarni qayta hisoblash
        setTimeout(() => recomputeSubjectsAndGroups(), 200);
    };
    semDD.onChange = (v) => recomputeSubjectsAndGroups();
    
    // Auto-select
    const defaultEducationType = EDUCATION_TYPES[0];
    const currentYear = YEARS.find(y => y.current == 1);
    
    if (defaultEducationType) {
        eduTypeInput.value = defaultEducationType.id;
        eduTypeDD.display.textContent = defaultEducationType.name;
        eduTypeDD.display.classList.remove('dropdown-placeholder');
    }
    
    if (currentYear) {
        yearInput.value = currentYear.id;
        yearDD.display.textContent = currentYear.name;
        yearDD.display.classList.remove('dropdown-placeholder');
        handleYearChange(currentYear.id);
    }
}

function handleEducationTypeChange(typeId) {
    // Filter departments if needed
}

function handleYearChange(yId) {
    const depts = DEPARTMENTS.filter(d => !d.education_year_id || String(d.education_year_id) === String(yId));
    deptDD.setOptions(depts.map(d => ({ value: d.id, label: d.name })));
    specDD.setOptions([]);
    subjDD.setOptions([]);
    groupsDD.setOptions([]);
}

function handleDepartmentChange(dId) {
    const eduTypeId = eduTypeInput.value;
    const yId = yearInput.value;
    
    let specs = SPECS.filter(s => String(s.department_id) === String(dId));
    
    const nameCount = {};
    const firstIdByName = {};
    
    specs.forEach(s => {
        if (!nameCount[s.name]) {
            nameCount[s.name] = 0;
            firstIdByName[s.name] = s.id;
        }
        
        const currCount = CURRICULUMS.filter(c => {
            return c.specialty_id === s.id &&
                   (!eduTypeId || String(c.education_type_id) === String(eduTypeId)) &&
                   (!yId || String(c.education_year_id) === String(yId));
        }).length;
        
        nameCount[s.name] += currCount;
    });
    
    const specOptions = Object.keys(nameCount).map(name => {
        const count = nameCount[name];
        const label = count > 1 ? `${name} (${count} variant)` : name;
        return {
            value: firstIdByName[name],
            label: label
        };
    });
    
    specDD.setOptions(specOptions);
    subjDD.setOptions([]);
    groupsDD.setOptions([]);
}

function handleSpecialtyChange(specId) {
    const selectedSpec = SPECS.find(s => String(s.id) === String(specId));
    if (selectedSpec) {
        const specsWithSameName = SPECS.filter(s => s.name === selectedSpec.name);
        const specIds = specsWithSameName.map(s => s.id);
        const curriculumsForThisSpec = CURRICULUMS.filter(c => specIds.includes(c.specialty_id));
    }
    
    recomputeSubjectsAndGroups();
}

function autoSelectCurrentSemester(levelId) {
    const yId = yearInput.value;
    
    if (!yId || !levelId) return;
    
    // Current semester ni topish
    const currentSem = CURRENT_SEMESTERS.find(cs => 
        String(cs.education_year_id) === String(yId) && 
        String(cs.level_id) === String(levelId)
    );
    
    if (currentSem && currentSem.semester_id) {
        const semester = SEMESTERS.find(s => String(s.id) === String(currentSem.semester_id));
        
        if (semester) {
            semInput.value = semester.id;
            semDD.display.textContent = semester.name;
            semDD.display.classList.remove('dropdown-placeholder');
            
            // SEMESTR O'ZGARGANDA FANLARNI YANGILASH
            setTimeout(() => recomputeSubjectsAndGroups(), 100);
            return;
        }
    }
    
    // Agar CURRENT_SEMESTERS da topilmasa, kurs nomi bo'yicha mapping
    const selectedLevel = LEVELS.find(l => String(l.id) === String(levelId));
    if (!selectedLevel) return;
    
    // Kurs nomi bo'yicha semestr mapping
    const COURSE_SEMESTER_MAP_BY_NAME = {
        "1-kurs": 73,  // 1-semestr
        "2-kurs": 75,  // 3-semestr  
        "3-kurs": 76,  // 5-semestr
        "4-kurs": 74,  // 7-semestr
        "5-kurs": 77,  // 9-semestr
        "6-kurs": 81   // 11-semestr
    };
    
    const defaultSemesterId = COURSE_SEMESTER_MAP_BY_NAME[selectedLevel.name];
    if (defaultSemesterId) {
        const semester = SEMESTERS.find(s => String(s.id) === String(defaultSemesterId));
        if (semester) {
            semInput.value = semester.id;
            semDD.display.textContent = semester.name;
            semDD.display.classList.remove('dropdown-placeholder');
            
            // SEMESTR O'ZGARGANDA FANLARNI YANGILASH
            setTimeout(() => recomputeSubjectsAndGroups(), 100);
        }
    }
}

function recomputeSubjectsAndGroups() {
    const eduTypeId = eduTypeInput.value;
    const yId = yearInput.value;
    const dId = deptInput.value;
    const sId = specInput.value;
    const lvlId = levelInput.value;
    const semId = semInput.value;
    
    console.log("=== TO'LIQ FILTRLASH (CURRICULUM YILI BILAN) ===");
    console.log("Ta'lim turi:", eduTypeId, "O'quv yili:", yId, "Fakultet:", dId, "Yo'nalish:", sId, "Kurs:", lvlId, "Semestr:", semId);
    
    // YANGI: Curriculum o'quv yilini hisoblash
    let curriculumYearId = yId; // Default: tanlangan yil
    
    if (lvlId && yId) {
        // Kurs nomidan raqamni olish (masalan: "2-kurs" → 2)
        const selectedLevel = LEVELS.find(l => String(l.id) === String(lvlId));
        if (selectedLevel && selectedLevel.name) {
            const courseMatch = selectedLevel.name.match(/^(\d+)/);
            if (courseMatch) {
                const courseNum = parseInt(courseMatch[1]); // "2-kurs" → 2
                
                // Tanlangan o'quv yilidan raqamni olish
                const selectedYear = YEARS.find(y => String(y.id) === String(yId));
                if (selectedYear && selectedYear.hemis_id) {
                    const yearNum = parseInt(selectedYear.hemis_id); // 2025
                    
                    // Curriculum o'quv yilini hisoblash: Year - (Kurs - 1)
                    const curriculumYearNum = yearNum - (courseNum - 1);
                    
                    console.log("Hisoblash:", yearNum, "-", "(", courseNum, "- 1 ) =", curriculumYearNum);
                    
                    // Curriculum o'quv yili ID sini topish
                    const curriculumYear = YEARS.find(y => parseInt(y.hemis_id) === curriculumYearNum);
                    
                    if (curriculumYear) {
                        curriculumYearId = curriculumYear.id;
                        console.log("Curriculum o'quv yili:", curriculumYear.name, "(ID:", curriculumYearId, ")");
                    } else {
                        console.log("⚠ Curriculum o'quv yili topilmadi:", curriculumYearNum);
                    }
                }
            }
        }
    }
    
    // Fanlarni filtrlash
    let subs = SUBJECTS.filter(s => {
        // MAJBURIY: Faqat active = 1 va at_semester = 1
        if (s.active != 1 || s.at_semester != 1) {
            return false;
        }
        
        // Ta'lim turi filtri
        if (eduTypeId && s.education_type_id != null) {
            if (String(s.education_type_id) !== String(eduTypeId)) {
                return false;
            }
        }
        
        // O'quv yili filtri - YANGI: Curriculum yilini ishlatamiz
        if (curriculumYearId && s.education_year_id != null) {
            if (String(s.education_year_id) !== String(curriculumYearId)) {
                return false;
            }
        }
        
        // Fakultet filtri
        if (dId && s.department_id != null) {
            if (String(s.department_id) !== String(dId)) {
                return false;
            }
        }
        
        // Yo'nalish filtri - YANGI: NOM bo'yicha izlash
        if (sId && s.specialty_id != null) {
            // Tanlangan yo'nalish nomini olish
            const selectedSpec = SPECS.find(spec => String(spec.id) === String(sId));
            if (selectedSpec && selectedSpec.name) {
                // Bir xil nomdagi barcha yo'nalishlarni topish
                const matchingSpecs = SPECS.filter(spec => spec.name === selectedSpec.name);
                const specIds = matchingSpecs.map(spec => String(spec.id));
                
                // Fan yo'nalishi shu ro'yxatda bormi?
                if (!specIds.includes(String(s.specialty_id))) {
                    return false;
                }
            } else {
                // Agar nom topilmasa, ID bo'yicha
                if (String(s.specialty_id) !== String(sId)) {
                    return false;
                }
            }
        }
        
        // Kurs filtri
        if (lvlId && s.level_id != null) {
            if (String(s.level_id) !== String(lvlId)) {
                return false;
            }
        }
        
        // Semestr filtri
        if (semId && s.semester_id != null) {
            if (String(s.semester_id) !== String(semId)) {
                return false;
            }
        }
        
        return true;
    });
    
    console.log("Filtr natijalari:", subs.length, "ta fan topildi");
    
    // DEBUG: Agar 0 ta fan bo'lsa
    if (subs.length === 0 && SUBJECTS.length > 0) {
        console.log("DEBUG: Birinchi 3 ta fan:", SUBJECTS.slice(0, 3));
        console.log("DEBUG: Filter qiymatlari:", {
            eduTypeId, 
            curriculumYearId: curriculumYearId + " (hisoblangan)", 
            dId, 
            sId, 
            lvlId, 
            semId
        });
        
        // Qancha fan active = 1?
        const activeSubjects = SUBJECTS.filter(s => s.active == 1 && s.at_semester == 1);
        console.log("DEBUG: Active fanlar soni:", activeSubjects.length);
        
        // Curriculum yili bo'yicha fanlar bormi?
        if (curriculumYearId) {
            const yearSubjects = SUBJECTS.filter(s => 
                s.active == 1 && 
                s.at_semester == 1 && 
                String(s.education_year_id) === String(curriculumYearId)
            );
            console.log("DEBUG: Curriculum yili", curriculumYearId, "uchun active fanlar:", yearSubjects.length);
        }
    }
    
    // Fanlar dropdown ni yangilash
    subs.sort((a, b) => a.name.localeCompare(b.name, 'uz'));
    
    subjDD.setOptions(subs.map(s => ({
        value: s.id,
        label: s.name
    })));
    
    // Guruhlarni filtrlash
    let grps = GROUPS.slice();

    if (eduTypeId) {
        grps = grps.filter(x => !x.education_type_id || String(x.education_type_id) === String(eduTypeId));
    }

    // O'quv yili uchun ham curriculumYearId ni ishlatish maqsadga muvofiq bo'lishi mumkin,
    // lekin guruhlar odatda joriy o'quv yiliga bog'langan. Agar sizda gruppalar
    // curriculum yiliga bog'langan bo'lsa, kerak bo'lsa shu yerda curriculumYearId ga almashtirasiz.
    if (yId) {
        grps = grps.filter(x => !x.education_year_id || String(x.education_year_id) === String(yId));
    }

    if (dId) {
        grps = grps.filter(x => !x.department_id || String(x.department_id) === String(dId));
    }

    // YO‘NALISH BO‘YICHA: NOM BO‘YICHA BARCHA VARIANTLARNI OLISh (xuddi fanlardagidek)
    if (sId) {
        const selectedSpec = SPECS.find(spec => String(spec.id) === String(sId));
        if (selectedSpec && selectedSpec.name) {
            const matchingSpecs = SPECS.filter(spec => spec.name === selectedSpec.name);
            const specIds = matchingSpecs.map(spec => String(spec.id));
            grps = grps.filter(x => !x.specialty_id || specIds.includes(String(x.specialty_id)));
        } else {
            grps = grps.filter(x => String(x.specialty_id) === String(sId));
        }
    }

    if (lvlId) {
        grps = grps.filter(x => !x.level_id || String(x.level_id) === String(lvlId));
    }

    if (semId) {
        grps = grps.filter(x => !x.semester_id || String(x.semester_id) === String(semId));
    }
    
    grps.sort((a, b) => a.name.localeCompare(b.name, 'uz'));
    
    groupsDD.setOptions(grps.map(g => ({ value: g.id, label: g.name })));
}

function updateData() {
    window.location.href = 'update_hemis_cache.php';
}

function exportExcel(type) {
    const btn = event.target.closest('.btn');
    const params = new URLSearchParams();
    
    const educationTypeId = document.querySelector('input[name="education_type_id"]')?.value;
    const educationYearId = document.querySelector('input[name="education_year_id"]')?.value;
    const departmentId = document.querySelector('input[name="department_id"]')?.value;
    const specialtyId = document.querySelector('input[name="specialty_id"]')?.value;
    const levelId = document.querySelector('input[name="level_id"]')?.value;
    const semesterId = document.querySelector('input[name="semester_id"]')?.value;
    
    const subjectIds = [];
    document.querySelectorAll('input[name="subject_ids[]"]:checked').forEach(cb => {
        subjectIds.push(cb.value);
    });
    
    const groupIds = [];
    document.querySelectorAll('input[name="group_ids[]"]:checked').forEach(cb => {
        groupIds.push(cb.value);
    });
    
    if (educationTypeId) params.append('education_type_id', educationTypeId);
    if (educationYearId) params.append('education_year_id', educationYearId);
    if (departmentId) params.append('department_id', departmentId);
    if (specialtyId) params.append('specialty_id', specialtyId);
    if (levelId) params.append('level_id', levelId);
    if (semesterId) params.append('semester_id', semesterId);
    params.append('type', type);
    
    if (subjectIds.length > 0) {
        params.append('subject_ids', subjectIds.join(','));
    }
    if (groupIds.length > 0) {
        params.append('group_ids', groupIds.join(','));
    }
    
    if (!educationYearId) {
        alert('⚠️ Iltimos, o\'quv yilini tanlang!');
        return;
    }
    
    const originalText = btn.textContent;
    btn.textContent = '⏳ Tayyorlanmoqda...';
    btn.disabled = true;
    
    // ✅ SHARTLI URL
    const url = (type === 'yn_oldi') 
        ? 'yn_oldi_word.php?' + params.toString()
        : 'excel_export.php?' + params.toString();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(text || 'Server xatosi');
                });
            }
            return response.blob();
        })
        .then(blob => {
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            
            // ✅ SHARTLI FAYL NOMI
            const fileExt = (type === 'yn_oldi') ? 'pdf' : 'xlsx';
            a.download = `hisobot_${type}_${new Date().toISOString().slice(0,10)}.${fileExt}`;
            
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(downloadUrl);
            document.body.removeChild(a);
            
            // ✅ SHARTLI XABAR
            const docType = (type === 'yn_oldi') ? 'PDF' : 'Excel';
            alert(`✅ ${docType} hisobot muvaffaqiyatli yuklandi!`);
        })
        .catch(error => {
            console.error('Export error:', error);
            alert('❌ Xatolik: ' + error.message);
        })
        .finally(() => {
            btn.textContent = originalText;
            btn.disabled = false;
        });
}
document.addEventListener('DOMContentLoaded', initFilters);
</script>

</body>
</html>
