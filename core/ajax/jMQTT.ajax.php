<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    ajax::init();

    if (init('action') == 'getTemplateList') {
        ajax::success(jMQTT::templateParameters());
    }

    if (init('action') == 'applyTemplate') {
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(__('Pas d\'équipement jMQTT avec l\'id fourni', __FILE__) . ' (id=' . init('id') . ')');
        }
        $eqpt->applyTemplate(init('name'), init('topic'), init('keepCmd'));
        ajax::success();
    }

    if (init('action') == 'createTemplate') {
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(__('Pas d\'équipement jMQTT avec l\'id fourni', __FILE__) . ' (id=' . init('id') . ')');
        }
        $eqpt->createTemplate(init('name'));
        ajax::success();
    }

    // To change the equipment automatic inclusion mode
    if (init('action') == 'changeIncludeMode') {
        $new_broker = jMQTT::getBrokerFromId(init('id'));
        $new_broker->changeIncludeMode(init('mode'));
        ajax::success();
    }

    if (init('action') == 'getDaemonInfo') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $new_broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($new_broker->getDaemonInfo());
    }
    
    if (init('action') == 'getDaemonState') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $new_broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($new_broker->getDaemonState());
    }
    
    if (init('action') == 'daemonStart') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $new_broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($new_broker->startDaemon(true));
    }

    if (init('action') == 'moveToBroker') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        /** @var jMQTT $eqpt */        
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(__('Pas d\'équipement jMQTT avec l\'id fourni', __FILE__) . ' (id=' . init('id') . ')');
        }
        $old_broker_id = $eqpt->getBrkId();
        $new_broker = jMQTT::getBrokerFromId(init('brk_id'));
        log::add('jMQTT', 'info', 'déplace l\'équipement ' . $eqpt->getName() . ' vers le broker ' . $new_broker->getName());
        $eqpt->setType(jMQTT::TYP_EQPT);
        $eqpt->setBrkId($new_broker->getId());
        $eqpt->cleanEquipment();
        $eqpt->save(true);
        
        // Restart the new daemon
        if ($new_broker->isDaemonToBeRestarted()) {
            $new_broker->startDaemon(true);
        }
        if ($old_broker_id > 0) {
            $old_broker = jMQTT::getBrokerFromId($old_broker_id);
            if ($old_broker->isDaemonToBeRestarted()) {
                $old_broker->startDaemon(true);
            }
        }
        
        ajax::success();
    }
    
    
    throw new Exception(__('Aucune methode Ajax correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>
