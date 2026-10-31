UPDATE {TABLE}
SET status = :status,
    make = :make,
    model = :model,
    year = :year,
    registration = :registration,
    mileage = :mileage,
    price = :price,
    engine = :engine,
    transmission = :transmission,
    fuel = :fuel,
    description = :description,
    features = :features,
    specs = :specs,
    is_published = :is_published,
    updated_at = NOW()
WHERE id = :id
