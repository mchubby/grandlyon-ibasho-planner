<?php // src/Zenon/Form/Type/GeoPoiType.php

namespace Zenon\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GeoPoiType extends AbstractType {

    public function __construct($nosqldoc) {
        $this->doc = $nosqldoc;
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->add('id', 'hidden', array('data'=>(string)$this->doc['_id']));
        foreach (self::getSchema() as $fieldname => $def) {
            if ($def[self::TYPE] !== false) {
                $opts = $def[self::OPTIONS];
                if (isset($this->doc[$fieldname])) {
                    if ($this->doc[$fieldname] instanceof \MongoDate) {
                        $opts['data'] = new \DateTime('@' . $this->doc[$fieldname]->sec);
                    } else {
                        $opts['data'] = $this->doc[$fieldname];
                    }
                }
                $builder->add($fieldname, $def[self::TYPE], $opts);
            }
        }
        $builder->add('save', 'submit');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver) {
        $resolver->setDefaults(array(
            'csrf_protection' => false,
        ));
    }

    public function getName() {
        return 'geopoi';
    }

    //--  not inherited from interface

    public function merge($postdoc) {
        foreach (self::getSchema() as $fieldname => $def) {
            if ($def[self::TYPE] !== false && isset($postdoc[$fieldname])) {
                if ($postdoc[$fieldname] instanceof \DateTime) {
                    $postdoc[$fieldname] = new \MongoDate($postdoc[$fieldname]->getTimestamp());
                } else {
                    $this->doc[$fieldname] = $postdoc[$fieldname];
                }
            }
        }
        return $this->doc;
    }


    private static $dbschema = null;
		const TYPE = 0;
		const OPTIONS = 1;
		const DEFVAL = 2;

    public static function getSchema() {
        if (self::$dbschema == null) {
            // REQUIRED field, validation not blank
    		$field_not_blank_default_empty = array(
    			self::TYPE => 'text',
    			self::OPTIONS => array(
    				'required' => true,
    				'constraints' => array(
    					new Assert\NotBlank()
    				),
    			),
    			self::DEFVAL => ''
    		);
            // non-required field, validation none
    		$field_any_default_empty = array(
    			self::TYPE => 'text',
    			self::OPTIONS => array(
    				'required' => false,
    				'constraints' => array(),
    			),
    			self::DEFVAL => ''
    		);

            // non-required field, validation none
    		$field_any_default_null = array(
    			self::TYPE => 'text',
    			self::OPTIONS => array(
    				'required' => false,
    				'constraints' => array(),
    			),
    			self::DEFVAL => NULL
    		);

            // non-required field, validation none
    		$fieldtxa_any_default_empty = array(
    			self::TYPE => 'textarea'
    		) + $field_any_default_empty;

            // REQUIRED field, validation integer
    		$fieldint_not_blank_default_zero = array(
    			self::TYPE => 'integer',
    			self::OPTIONS => array(
    				'required' => true,
    				'constraints' => array(
    					new Assert\NotBlank(),
    					new Assert\Type(array(
                            'type' => 'integer'
                        )),
    				),
    			),
    			self::DEFVAL => 0
    		);

    		$fieldint_any_default_zero = array(
    			self::TYPE => 'integer',
    			self::DEFVAL => 0
    		) + $field_any_default_null;

    		$fieldint_any_default_null = array(
    			self::TYPE => 'integer'
    		) + $field_any_default_null;

    		$fielddat_any_default_null = array(
    			self::TYPE => 'date',
    			self::OPTIONS => array(
    				'required' => false,
                    'widget' => 'single_text',
    				'constraints' => array(),
    			),
    			self::DEFVAL => NULL
    		);

            self::$dbschema = array(
                'mta_title'    => $field_not_blank_default_empty,
                'mta_url1'     => $field_any_default_empty,
                'mta_url2'     => $field_any_default_empty,
                'mta_notes'    => $fieldtxa_any_default_empty,
                'mta_desc1'    => $fieldtxa_any_default_empty,
                'mta_desc2'    => $fieldtxa_any_default_empty,
                'mta_dateins'  => $fielddat_any_default_null,
                'mta_datepub'  => $fielddat_any_default_null,  // dateof first purported ad
                'mta_datemaj'  => $fielddat_any_default_null,  // dateof last refreshed
                
                'apt_addr'     => $field_any_default_null,
                'apt_prix'     => $fieldint_not_blank_default_zero,
                'apt_surface'  => $fieldint_not_blank_default_zero,  // -> prix/m2, charg/m2
                'apt_anconstr' => $fieldint_any_default_null,  // annee/decennie
                'apt_numetage' => $fieldint_any_default_null,
                'apt_totetage' => $fieldint_any_default_null,
                'apt_cave'     => $field_any_default_null,
                'apt_typgarag' => $field_any_default_null,  // coll/aerien/box
                'apt_typcuisn' => $field_any_default_null,  // 0/eqp/us
                'apt_typsdb'   => $field_any_default_null,  // bain/douche/ita
                'apt_typwc'    => $field_any_default_null,  // indiv/sdb/handi
                
                'apt_deducdep' => $fieldint_any_default_zero,  // eval depend. -> prix/m2
                
                'apt_charg'    => $fieldint_any_default_null,  // montant mens
                'apt_impotf'   => $fieldint_any_default_null,  // fonc
                'apt_impota'   => $fieldint_any_default_null,  // hab
                
                'eva_presta'   => $fieldint_any_default_null,  // presta, vetuste, situ ds imm/xpo
                'eva_prixrel'  => $fieldint_any_default_null,  // % a moy
                'eva_copro'    => $fieldint_any_default_null,  // niv charge, entret. comm
                'eva_envsitu'  => $fieldint_any_default_null,  // situ ds quart, desserte
                'eva_commod'   => $fieldint_any_default_null,  // prox commerce
                'eva_scolar'   => $fieldint_any_default_null,
                
                'pos' => array(
					self::TYPE => false,
                    self::OPTIONS => array(),
					self::DEFVAL => NULL
				),
            );
        }
        return self::$dbschema;
    }

    public static function blankDocument() {
		$schdefval = array();
		foreach (self::getSchema() as $field => $def) {
			$schdefval[$field] = $def[self::DEFVAL];
		}
        return $schdefval;
	}

}
