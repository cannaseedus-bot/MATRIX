-- Brain Mesh Orchestrator Schema
-- CM-1 Micronaut System for PHP Broker
-- @law ASX = XCFE = XJSON = KUHUL = AST = CM-1

-- Brains registry
CREATE TABLE IF NOT EXISTS brains (
    id VARCHAR(50) PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    description TEXT,
    horizontal_fold TINYINT DEFAULT 0,
    priority TINYINT DEFAULT 5,
    capabilities JSON,
    domains JSON,
    llm_provider VARCHAR(50),
    llm_model VARCHAR(100),
    llm_fallback VARCHAR(100),
    trigger_conditions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Brain scores (adaptive learning)
CREATE TABLE IF NOT EXISTS brain_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brain_id VARCHAR(50) NOT NULL,
    domain VARCHAR(100) NOT NULL,
    score DECIMAL(10,6) DEFAULT 0.500000,
    success_rate DECIMAL(10,6) DEFAULT 0.500000,
    avg_latency_ms INT DEFAULT 1000,
    total_executions INT DEFAULT 0,
    total_successes INT DEFAULT 0,
    total_failures INT DEFAULT 0,
    tier ENUM('promoted', 'standard', 'demoted') DEFAULT 'standard',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_brain_domain (brain_id, domain),
    INDEX idx_domain (domain),
    INDEX idx_score (score DESC),
    INDEX idx_tier (tier),
    FOREIGN KEY (brain_id) REFERENCES brains(id) ON DELETE CASCADE
);

-- Execution history (rolling window for scoring)
CREATE TABLE IF NOT EXISTS brain_executions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brain_id VARCHAR(50) NOT NULL,
    domain VARCHAR(100) NOT NULL,
    task_id INT,
    success BOOLEAN NOT NULL,
    latency_ms INT NOT NULL,
    error_message TEXT,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_brain_domain (brain_id, domain),
    INDEX idx_created (created_at),
    FOREIGN KEY (brain_id) REFERENCES brains(id) ON DELETE CASCADE
);

-- CM-1 phase tracking
CREATE TABLE IF NOT EXISTS cm1_phases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    from_phase VARCHAR(50),
    to_phase VARCHAR(50) NOT NULL,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_phase (to_phase)
);

-- Error queue for self-healing
CREATE TABLE IF NOT EXISTS error_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brain_id VARCHAR(50),
    task_id INT,
    error_type VARCHAR(100) NOT NULL,
    error_message TEXT NOT NULL,
    context JSON,
    retry_count TINYINT DEFAULT 0,
    max_retries TINYINT DEFAULT 3,
    status ENUM('pending', 'processing', 'resolved', 'escalated') DEFAULT 'pending',
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_brain (brain_id)
);

-- Routing rules cache
CREATE TABLE IF NOT EXISTS routing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(100) NOT NULL UNIQUE,
    brain_id VARCHAR(50) NOT NULL,
    priority INT DEFAULT 0,
    conditions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain (domain),
    FOREIGN KEY (brain_id) REFERENCES brains(id) ON DELETE CASCADE
);

-- Insert default brains from brains.json
INSERT INTO brains (id, label, type, description, horizontal_fold, priority, capabilities, domains, llm_provider, llm_model, llm_fallback, trigger_conditions) VALUES
('mx2lm_prime', 'MX2LM Prime', 'orchestrator', 'Primary orchestration brain - routes tasks, manages lifecycle', 0, 1,
 '["task_routing", "lifecycle_management", "error_detection", "brain_coordination"]',
 '["orchestration", "routing", "coordination"]',
 'claude', 'claude-sonnet-4-20250514', 'ollama:llama3.2',
 '["startup", "task_dispatch", "brain_failure", "coordination_required"]'),

('scxq2_cipher', 'SCXQ2 Cipher', 'compression', 'Compression and encoding specialist - SCXQ2 templates', 3, 2,
 '["compression", "template_matching", "glyph_extraction", "quantum_encoding"]',
 '["compression_optimization", "template_library", "encoding"]',
 'ollama', 'codellama', 'ollama:llama3.2',
 '["compression_request", "template_not_found", "encoding_failure"]'),

('emerald_ghost', 'Emerald Ghost', 'ui_design', 'UI/UX specialist - CSS, theming, visual design', 2, 3,
 '["css_generation", "theme_design", "atomic_tokens", "glass_morphism", "accessibility"]',
 '["ui_design_emerald", "css_atomic", "visual_design"]',
 'claude', 'claude-sonnet-4-20250514', 'ollama:llama3.2',
 '["css_generation_request", "theme_modification", "ui_error"]'),

('quantum_swarm', 'Quantum Swarm', 'game_logic', 'Game logic and rules specialist - TES/DOOM mechanics', 4, 4,
 '["game_rules", "physics_simulation", "entity_behavior", "quantum_states"]',
 '["tes_doom_rules_quantum", "game_mechanics", "simulation"]',
 'ollama', 'mixtral', 'ollama:llama3.2',
 '["game_rule_request", "physics_calculation", "entity_spawn"]'),

('kuhul_runtime', 'KUHUL Runtime', 'runtime', 'Runtime execution specialist - kernel operations, CM-1 control', 1, 2,
 '["kernel_execution", "cm1_control", "xcfe_routing", "phase_management"]',
 '["runtime_orchestration", "kernel_ops", "cm1_control"]',
 'codex', 'gpt-4', 'claude',
 '["kernel_error", "phase_violation", "xcfe_failure"]'),

('css_atomic', 'CSS Atomic Agent', 'css_specialist', 'Atomic CSS token generation and normalization', 2, 5,
 '["token_generation", "variable_normalization", "design_system"]',
 '["atomic.css", "design_tokens"]',
 'claude', 'claude-haiku-4-20250514', 'ollama:llama3.2',
 '["css.atomic.*"]'),

('css_xcfe', 'CSS XCFE Agent', 'css_specialist', 'XCFE control flow to CSS class mapping', 1, 5,
 '["control_mapping", "state_classes", "cm1_css_binding"]',
 '["xcfe.css", "control_classes"]',
 'ollama', 'codellama', 'ollama:llama3.2',
 '["css.xcfe.*"]'),

('css_runtime', 'CSS Runtime Agent', 'css_specialist', 'Runtime CSS physics and state management', 1, 5,
 '["css_physics", "runtime_state", "animation"]',
 '["runtime.css", "css_physics"]',
 'ollama', 'llama3.2', NULL,
 '["css.runtime.*"]'),

('css_svg_3d', 'CSS SVG 3D Agent', 'css_specialist', 'SVG and 3D CSS rendering specialist', 4, 5,
 '["svg_generation", "3d_transforms", "scene_rendering"]',
 '["svg_3d.css", "3d_rendering"]',
 'claude', 'claude-sonnet-4-20250514', 'ollama:codellama',
 '["css.svg3d.*"]')

ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Insert default routing rules
INSERT INTO routing_rules (domain, brain_id, priority) VALUES
('compression_optimization', 'scxq2_cipher', 1),
('ui_design_emerald', 'emerald_ghost', 1),
('tes_doom_rules_quantum', 'quantum_swarm', 1),
('runtime_orchestration', 'kuhul_runtime', 1),
('orchestration', 'mx2lm_prime', 1),
('atomic.css', 'css_atomic', 1),
('xcfe.css', 'css_xcfe', 1),
('runtime.css', 'css_runtime', 1),
('svg_3d.css', 'css_svg_3d', 1)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Initialize default scores for all brains
INSERT INTO brain_scores (brain_id, domain, score, success_rate, tier)
SELECT b.id, 'general', 0.5, 0.5, 'standard'
FROM brains b
ON DUPLICATE KEY UPDATE updated_at = NOW();
