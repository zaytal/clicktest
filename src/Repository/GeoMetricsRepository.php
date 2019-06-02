<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;


class GeoMetricsRepository extends EntityRepository
{
    /**
     * Добавляет новую запись в таблицу geo_metrics ИЛИ
     * если запись с ключём (date, geo, zone) уже существует,
     * то в существующей строке прибавить к impressions и revenue новые добавляемые значения
     *
     * @param $geo_data
     *
     * @return bool Результат работы.
     * Возвращает true - если данные добавились/обновились успешно, false - в противном случае.
     */
    public function upsertGeoMetrics($geo_data) :bool
    {
        $rsm = new ResultSetMapping();
        $ar_insert_values = [];

        //@todo можно вынести в отдельный метод/класс
        foreach($geo_data as $r_key => $table_row) {
            $ar_placeholders = [];
            foreach($table_row as $t_key => $table_field) {
                $ar_placeholders[] = ":p_{$r_key}_{$t_key}";
            }
            if(!empty($ar_placeholders)) {
                $insert_values = '(' . implode(",", $ar_placeholders) . ')';
                $ar_insert_values[] = $insert_values;
            }
        }

        if(!empty($ar_insert_values)) {
            $str_insert_placeholders = implode(",", $ar_insert_values);
            $sql = "
                INSERT INTO geo_metrics (date, geo, zone, impressions, revenue)
                VALUES {$str_insert_placeholders}
                ON CONFLICT (date, geo, zone) DO UPDATE
                  SET impressions = (geo_metrics.impressions + excluded.impressions),
                    revenue = (geo_metrics.revenue + excluded.revenue);
            ";

            $em = $this->getEntityManager();
            $em_query = $em->createNativeQuery($sql, $rsm);

            //@todo можно также вынести в отдельный метод/класс
            foreach($geo_data as $r_key => $table_row) {
                foreach($table_row as $t_key => $table_field) {
                    $em_query->setParameter(":p_{$r_key}_{$t_key}", $table_field);
                }
            }

            $exec_result = $em_query->getResult();

            return is_null($exec_result) || $exec_result === false ? false : true;
        } else {
            return false;
        }
    }

}
