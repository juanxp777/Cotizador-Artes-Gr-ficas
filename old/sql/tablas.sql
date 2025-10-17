-- Tabla de parámetros de costos
CREATE TABLE parametros_costos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    valor DECIMAL(12,2) NOT NULL,
    descripcion TEXT,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de acabados
CREATE TABLE acabados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_acabado VARCHAR(50) NOT NULL,
    min_cantidad INT NOT NULL,
    max_cantidad INT NOT NULL,
    costo DECIMAL(10,2) NOT NULL
);

-- Insertar datos iniciales
INSERT INTO parametros_costos (nombre, valor, descripcion) VALUES
('costo_plancha_cmyk_cuarto', 40000, 'Costo por plancha CMYK cuarto pliego'),
('costo_plancha_cmyk_medio', 80000, 'Costo por plancha CMYK medio pliego'),
('costo_plancha_1color_medio', 20000, 'Costo por plancha 1 color medio pliego'),
('cantidad_grande_digital', 500, 'Cantidad mínima para precio especial digital'),
('costo_tiraje_cuarto_cmyk', 50000, 'Costo de tiraje por mil cuarto pliego CMYK'),
('costo_tiraje_medio_cmyk', 80000, 'Costo de tiraje por mil medio pliego CMYK'),
('costo_tiraje_medio_1color', 30000, 'Costo de tiraje por mil medio pliego 1 color'),
('papel_propalcote_150g', 600, 'Costo por pliego de papel propalcote 150g'),
('papel_bond_75g', 400, 'Costo por pliego de papel bond 75g'),
('costo_clic_color_normal', 700, 'Costo por clic color normal'),
('costo_clic_color_grande', 500, 'Costo por clic color para grandes cantidades'),
('costo_clic_bw_normal', 200, 'Costo por clic blanco y negro normal'),
('costo_clic_bw_grande', 100, 'Costo por clic blanco y negro para grandes cantidades');

INSERT INTO acabados (nombre_acabado, min_cantidad, max_cantidad, costo) VALUES
('Anillado', 1, 50, 3000),
('Anillado', 51, 200, 2500),
('Anillado', 201, 9999, 2000),
('Grapado', 1, 500, 200),
('Grapado', 501, 9999, 150);