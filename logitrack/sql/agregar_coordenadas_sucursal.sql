-- Agregar columnas de coordenadas a la tabla sucursal
ALTER TABLE sucursal
  ADD COLUMN latitud  DECIMAL(10, 7) DEFAULT NULL,
  ADD COLUMN longitud DECIMAL(10, 7) DEFAULT NULL;

-- Actualizar las 48 sucursales con coordenadas reales de ciudades argentinas
UPDATE sucursal SET latitud = -34.6037, longitud = -58.3816 WHERE id_sucursal = 1;  -- Buenos Aires (Centro)
UPDATE sucursal SET latitud = -34.5795, longitud = -58.4335 WHERE id_sucursal = 2;  -- Buenos Aires (Palermo)
UPDATE sucursal SET latitud = -34.6341, longitud = -58.3624 WHERE id_sucursal = 3;  -- Buenos Aires (La Boca)
UPDATE sucursal SET latitud = -34.5600, longitud = -58.4596 WHERE id_sucursal = 4;  -- Buenos Aires (Belgrano)
UPDATE sucursal SET latitud = -34.9205, longitud = -57.9536 WHERE id_sucursal = 5;  -- La Plata
UPDATE sucursal SET latitud = -34.7255, longitud = -58.2638 WHERE id_sucursal = 6;  -- Quilmes
UPDATE sucursal SET latitud = -34.7650, longitud = -58.4003 WHERE id_sucursal = 7;  -- Lomas de Zamora
UPDATE sucursal SET latitud = -34.6503, longitud = -58.6197 WHERE id_sucursal = 8;  -- Morón
UPDATE sucursal SET latitud = -31.4201, longitud = -64.1888 WHERE id_sucursal = 9;  -- Córdoba (Centro)
UPDATE sucursal SET latitud = -31.4300, longitud = -64.1950 WHERE id_sucursal = 10; -- Córdoba (Nueva Córdoba)
UPDATE sucursal SET latitud = -32.4078, longitud = -63.2435 WHERE id_sucursal = 11; -- Villa María
UPDATE sucursal SET latitud = -33.1232, longitud = -64.3493 WHERE id_sucursal = 12; -- Río Cuarto
UPDATE sucursal SET latitud = -31.4278, longitud = -62.0853 WHERE id_sucursal = 13; -- San Francisco (Córdoba)
UPDATE sucursal SET latitud = -32.9442, longitud = -60.6505 WHERE id_sucursal = 14; -- Rosario (Centro)
UPDATE sucursal SET latitud = -32.9100, longitud = -60.6700 WHERE id_sucursal = 15; -- Rosario (Norte)
UPDATE sucursal SET latitud = -31.6333, longitud = -60.7000 WHERE id_sucursal = 16; -- Santa Fe
UPDATE sucursal SET latitud = -31.2519, longitud = -61.4875 WHERE id_sucursal = 17; -- Rafaela
UPDATE sucursal SET latitud = -33.7500, longitud = -61.9667 WHERE id_sucursal = 18; -- Venado Tuerto
UPDATE sucursal SET latitud = -32.8908, longitud = -68.8272 WHERE id_sucursal = 19; -- Mendoza (Centro)
UPDATE sucursal SET latitud = -32.8900, longitud = -68.7700 WHERE id_sucursal = 20; -- Mendoza (Este)
UPDATE sucursal SET latitud = -31.5375, longitud = -68.5364 WHERE id_sucursal = 21; -- San Juan
UPDATE sucursal SET latitud = -33.2950, longitud = -66.3356 WHERE id_sucursal = 22; -- San Luis
UPDATE sucursal SET latitud = -26.8083, longitud = -65.2176 WHERE id_sucursal = 23; -- Tucumán
UPDATE sucursal SET latitud = -24.7821, longitud = -65.4232 WHERE id_sucursal = 24; -- Salta
UPDATE sucursal SET latitud = -24.1858, longitud = -65.2995 WHERE id_sucursal = 25; -- Jujuy
UPDATE sucursal SET latitud = -27.7834, longitud = -64.2643 WHERE id_sucursal = 26; -- Santiago del Estero
UPDATE sucursal SET latitud = -28.4696, longitud = -65.7852 WHERE id_sucursal = 27; -- Catamarca
UPDATE sucursal SET latitud = -29.4131, longitud = -66.8558 WHERE id_sucursal = 28; -- La Rioja
UPDATE sucursal SET latitud = -27.4514, longitud = -58.9867 WHERE id_sucursal = 29; -- Resistencia (Chaco)
UPDATE sucursal SET latitud = -27.4806, longitud = -58.8341 WHERE id_sucursal = 30; -- Corrientes
UPDATE sucursal SET latitud = -27.3621, longitud = -55.8975 WHERE id_sucursal = 31; -- Posadas (Misiones)
UPDATE sucursal SET latitud = -26.1775, longitud = -58.1781 WHERE id_sucursal = 32; -- Formosa
UPDATE sucursal SET latitud = -31.7333, longitud = -60.5333 WHERE id_sucursal = 33; -- Paraná (Entre Ríos)
UPDATE sucursal SET latitud = -31.3920, longitud = -58.0210 WHERE id_sucursal = 34; -- Concordia (Entre Ríos)
UPDATE sucursal SET latitud = -33.0092, longitud = -58.5153 WHERE id_sucursal = 35; -- Gualeguaychú
UPDATE sucursal SET latitud = -38.7183, longitud = -62.2661 WHERE id_sucursal = 36; -- Bahía Blanca
UPDATE sucursal SET latitud = -38.0023, longitud = -57.5575 WHERE id_sucursal = 37; -- Mar del Plata
UPDATE sucursal SET latitud = -37.3167, longitud = -59.1333 WHERE id_sucursal = 38; -- Tandil
UPDATE sucursal SET latitud = -36.8923, longitud = -60.3224 WHERE id_sucursal = 39; -- Olavarría
UPDATE sucursal SET latitud = -38.9516, longitud = -68.0591 WHERE id_sucursal = 40; -- Neuquén
UPDATE sucursal SET latitud = -41.1335, longitud = -71.3103 WHERE id_sucursal = 41; -- Bariloche
UPDATE sucursal SET latitud = -39.0333, longitud = -67.5833 WHERE id_sucursal = 42; -- General Roca
UPDATE sucursal SET latitud = -38.9333, longitud = -67.9833 WHERE id_sucursal = 43; -- Cipolletti
UPDATE sucursal SET latitud = -43.2503, longitud = -65.3039 WHERE id_sucursal = 44; -- Trelew (Chubut)
UPDATE sucursal SET latitud = -42.7654, longitud = -65.0355 WHERE id_sucursal = 45; -- Puerto Madryn
UPDATE sucursal SET latitud = -45.8662, longitud = -67.5030 WHERE id_sucursal = 46; -- Comodoro Rivadavia
UPDATE sucursal SET latitud = -51.6226, longitud = -69.2181 WHERE id_sucursal = 47; -- Río Gallegos
UPDATE sucursal SET latitud = -54.8019, longitud = -68.3030 WHERE id_sucursal = 48; -- Ushuaia (Tierra del Fuego)
